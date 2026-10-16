<?php

use App\Models\Sale;
use App\Models\User;
use App\Support\BusinessDay;
use App\Support\NumberSequence;
use Carbon\CarbonImmutable;
use Database\Seeders\RolesAndPermissionsSeeder;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\seed;

beforeEach(function () {
    seed(RolesAndPermissionsSeeder::class);
});

// ── BusinessDay helper ───────────────────────────────────────────────────────

it('returns today when start time is 00:00', function () {
    $now = CarbonImmutable::parse('2026-10-11 14:00:00', 'UTC');
    $date = BusinessDay::current($now);

    expect($date->toDateString())->toBe('2026-10-11');
});

it('returns previous day when time is before start cutover', function () {
    // business day starts at 04:00; a sale at 01:30 belongs to the previous day
    settings()->set(['general.business_day_start' => '04:00']);

    $now = CarbonImmutable::parse('2026-10-11 01:30:00', 'UTC');
    $date = BusinessDay::current($now);

    expect($date->toDateString())->toBe('2026-10-10');
});

it('returns current day when time is at or after the start cutover', function () {
    settings()->set(['general.business_day_start' => '04:00']);

    $now = CarbonImmutable::parse('2026-10-11 04:00:00', 'UTC');
    $date = BusinessDay::current($now);

    expect($date->toDateString())->toBe('2026-10-11');
});

// ── Order number assignment ──────────────────────────────────────────────────

it('assigns order_no=1 on the first checkout of a business day', function () {
    $cashier = User::factory()->create()->assignRole('Cashier');
    $product = createSellableProduct();

    $sale = checkoutCart($cashier, $product);

    expect($sale->order_no)->toBe(1)
        ->and($sale->business_date)->not->toBeNull();
});

it('assigns consecutive order numbers for the same business day', function () {
    $cashier = User::factory()->create()->assignRole('Cashier');
    $product = createSellableProduct();

    $sale1 = checkoutCart($cashier, $product);
    $sale2 = checkoutCart($cashier, $product);

    expect($sale1->order_no)->toBe(1)
        ->and($sale2->order_no)->toBe(2);
});

it('resets to 1 on a new business date', function () {
    $cashier = User::factory()->create()->assignRole('Cashier');
    $product = createSellableProduct();

    // Manually set a sequence for yesterday so "today" starts fresh.
    $yesterday = CarbonImmutable::yesterday()->toDateString();
    NumberSequence::next("order:web:{$yesterday}");  // yesterday = 1

    $sale = checkoutCart($cashier, $product);

    expect($sale->order_no)->toBe(1);
});

it('a rolled-back checkout does not consume an order number', function () {
    $cashier = User::factory()->create()->assignRole('Cashier');
    $product = createSellableProduct();

    $today = CarbonImmutable::today()->toDateString();
    $key = "order:web:{$today}";

    $before = \Illuminate\Support\Facades\DB::table('number_sequences')->where('name', $key)->value('value') ?? 0;

    // Attempt a checkout that will fail (empty cart).
    actingAs($cashier)->postJson('/sales', [
        'items' => [],
        'payment_method' => 'cash',
        'cash_received' => '100',
    ])->assertUnprocessable();

    $after = \Illuminate\Support\Facades\DB::table('number_sequences')->where('name', $key)->value('value') ?? 0;

    // No number should have been consumed.
    expect($after)->toBe($before);
});

it('a held sale gets no order_no until checkout', function () {
    $cashier = User::factory()->create()->assignRole('Cashier');
    $product = createSellableProduct();

    $held = holdCart($cashier, $product);
    expect($held->order_no)->toBeNull();
});

it('receipt order_no equals KOT order_no', function () {
    $cashier = User::factory()->create()->assignRole('Cashier');
    $product = createSellableProductWithKitchen();

    $sale = checkoutCart($cashier, $product);
    $sale->load('kitchenOrders');

    expect($sale->order_no)->not->toBeNull()
        ->and($sale->kitchenOrders->first()->order_no)->toBe($sale->order_no);
});

it('invoice numbers keep increasing across business days', function () {
    $cashier = User::factory()->create()->assignRole('Cashier');
    $product = createSellableProduct();

    $sale1 = checkoutCart($cashier, $product);
    $sale2 = checkoutCart($cashier, $product);

    // Invoice sequence never resets.
    expect($sale2->invoice_number)->not->toBe($sale1->invoice_number);
    $n1 = (int) ltrim(substr($sale1->invoice_number, (int) strpos($sale1->invoice_number, '-') + 1), '0');
    $n2 = (int) ltrim(substr($sale2->invoice_number, (int) strpos($sale2->invoice_number, '-') + 1), '0');
    expect($n2)->toBeGreaterThan($n1);
});

// ── Helpers ──────────────────────────────────────────────────────────────────

function createSellableProduct(): \App\Models\Product
{
    return \App\Models\Product::factory()->create([
        'selling_price' => '100.00',
        'is_active' => true,
        'tax_rate' => 0,
    ]);
}

function createSellableProductWithKitchen(): \App\Models\Product
{
    $station = \App\Models\KitchenStation::create(['name' => 'Test Kitchen', 'is_active' => true, 'sort_order' => 1]);
    return \App\Models\Product::factory()->create([
        'selling_price' => '100.00',
        'is_active' => true,
        'tax_rate' => 0,
        'kitchen_station_id' => $station->id,
    ]);
}

function checkoutCart(User $cashier, \App\Models\Product $product): Sale
{
    $response = actingAs($cashier)->postJson('/sales', [
        'items' => [
            ['product_id' => $product->id, 'variant_id' => null, 'quantity' => 1, 'modifier_ids' => [], 'discount_type' => null, 'discount_value' => '0'],
        ],
        'order_type' => 'take_away',
        'payment_method' => 'cash',
        'cash_received' => '100.00',
    ]);

    $response->assertCreated();

    return Sale::find($response->json('sale.id'));
}

function holdCart(User $cashier, \App\Models\Product $product): Sale
{
    $response = actingAs($cashier)->postJson('/pos/held', [
        'items' => [
            ['product_id' => $product->id, 'variant_id' => null, 'quantity' => 1, 'modifier_ids' => [], 'discount_type' => null, 'discount_value' => '0'],
        ],
        'order_type' => 'take_away',
    ]);

    $response->assertCreated();

    return Sale::latest('id')->first();
}
