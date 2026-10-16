<?php

use App\Enums\KitchenStatus;
use App\Enums\SaleReturnStatus;
use App\Models\KitchenOrder;
use App\Models\Product;
use App\Models\Sale;
use App\Models\SaleEdit;
use App\Models\User;
use App\Services\SaleService;
use Database\Seeders\RolesAndPermissionsSeeder;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\seed;

beforeEach(function () {
    seed(RolesAndPermissionsSeeder::class);
});

// ── Helpers ───────────────────────────────────────────────────────────────────

function editProduct(): Product
{
    return Product::factory()->create([
        'selling_price' => '100.00',
        'is_active' => true,
        'tax_rate' => 0,
    ]);
}

function editProductWithStation(): Product
{
    $station = \App\Models\KitchenStation::create(['name' => 'Edit Kitchen', 'is_active' => true, 'sort_order' => 1]);

    return Product::factory()->create([
        'selling_price' => '100.00',
        'is_active' => true,
        'tax_rate' => 0,
        'kitchen_station_id' => $station->id,
    ]);
}

function editCheckout(User $user, Product $product): Sale
{
    $response = actingAs($user)->postJson('/sales', [
        'items' => [
            ['product_id' => $product->id, 'variant_id' => null, 'quantity' => 1,
                'modifier_ids' => [], 'discount_type' => null, 'discount_value' => '0'],
        ],
        'order_type' => 'take_away',
        'payment_method' => 'cash',
        'cash_received' => '100.00',
    ]);

    $response->assertCreated();

    return Sale::find($response->json('sale.id'));
}

function editPatch(User $user, Sale $sale, array $extra = []): \Illuminate\Testing\TestResponse
{
    return actingAs($user)->patch(route('sales.update', $sale), array_merge([
        'reason' => 'Test edit reason',
    ], $extra));
}

// ── Authorization ─────────────────────────────────────────────────────────────

it('cashier cannot access the edit form', function () {
    $cashier = userWith('Cashier');
    $admin = userWith('Admin');
    $product = editProduct();
    $sale = editCheckout($admin, $product);

    actingAs($cashier)->get(route('sales.edit', $sale))->assertForbidden();
});

it('cashier cannot patch a sale', function () {
    $cashier = userWith('Cashier');
    $admin = userWith('Admin');
    $product = editProduct();
    $sale = editCheckout($admin, $product);

    editPatch($cashier, $sale)->assertForbidden();
});

it('admin can access the edit form for a completed sale', function () {
    $admin = userWith('Admin');
    $product = editProduct();
    $sale = editCheckout($admin, $product);

    actingAs($admin)->get(route('sales.edit', $sale))->assertOk();
});

// ── Guard rails ───────────────────────────────────────────────────────────────

it('held sale cannot be edited', function () {
    $admin = userWith('Admin');

    $service = app(SaleService::class);
    $held = $service->hold([
        'items' => [
            ['product_id' => editProduct()->id, 'variant_id' => null, 'quantity' => 1,
                'modifier_ids' => [], 'discount_type' => null, 'discount_value' => '0'],
        ],
        'order_type' => 'take_away',
    ], $admin);

    actingAs($admin)->get(route('sales.edit', $held))->assertForbidden();
});

it('returned sale cannot be edited', function () {
    $admin = userWith('Admin');
    $product = editProduct();
    $sale = editCheckout($admin, $product);

    $sale->forceFill(['return_status' => SaleReturnStatus::Full])->save();

    actingAs($admin)->get(route('sales.edit', $sale))->assertForbidden();
});

it('reason is required', function () {
    $admin = userWith('Admin');
    $product = editProduct();
    $sale = editCheckout($admin, $product);

    actingAs($admin)->patch(route('sales.update', $sale), [
        'customer_name' => 'Ahmed',
    ])->assertSessionHasErrors(['reason']);
});

// ── Core edit behaviour ───────────────────────────────────────────────────────

it('updates customer name and writes audit row', function () {
    $admin = userWith('Admin');
    $product = editProduct();
    $sale = editCheckout($admin, $product);

    editPatch($admin, $sale, ['customer_name' => 'Maria'])->assertRedirect();

    $sale->refresh();
    expect($sale->customer_name)->toBe('Maria');

    $edit = SaleEdit::where('sale_id', $sale->id)->first();
    expect($edit)->not->toBeNull()
        ->and($edit->reason)->toBe('Test edit reason')
        ->and($edit->user_id)->toBe($admin->id);
});

it('increments edit_version on each edit', function () {
    $admin = userWith('Admin');
    $product = editProduct();
    $sale = editCheckout($admin, $product);

    expect($sale->edit_version)->toBe(0);

    editPatch($admin, $sale, ['customer_name' => 'First'])->assertRedirect();
    $sale->refresh();
    expect($sale->edit_version)->toBe(1);

    editPatch($admin, $sale, ['customer_name' => 'Second'])->assertRedirect();
    $sale->refresh();
    expect($sale->edit_version)->toBe(2);
});

it('audit before snapshot matches original total', function () {
    $admin = userWith('Admin');
    $product = editProduct();
    $sale = editCheckout($admin, $product);

    $originalTotal = (string) $sale->total;

    editPatch($admin, $sale, ['customer_name' => 'Snapshot Test'])->assertRedirect();

    $edit = SaleEdit::where('sale_id', $sale->id)->first();
    expect($edit->before['total'])->toBe($originalTotal);
});

it('existing line keeps its snapshot price when quantity changes', function () {
    $admin = userWith('Admin');
    $product = editProduct();
    $sale = editCheckout($admin, $product);

    $originalUnitPrice = (string) $sale->items->first()->unit_price;

    // Change the catalog price — it must NOT affect the saved line.
    $product->forceFill(['selling_price' => '999.00'])->save();

    $existingItem = $sale->items->first();

    editPatch($admin, $sale, [
        'items' => [
            ['existing_id' => $existingItem->id, 'quantity' => 2],
        ],
        'payment' => [
            'payment_method' => 'cash',
            'cash_received' => '200.00',
        ],
    ])->assertRedirect();

    $sale->refresh()->load('items');
    expect((string) $sale->items->first()->unit_price)->toBe($originalUnitPrice);
});

it('fails payment validation when total increases but old cash is reused', function () {
    $admin = userWith('Admin');
    $product = editProduct();
    $sale = editCheckout($admin, $product);

    $existingItem = $sale->items->first();

    // Web form: validation failure redirects back with session errors, not 422.
    editPatch($admin, $sale, [
        'items' => [
            ['existing_id' => $existingItem->id, 'quantity' => 2],
        ],
        'payment' => [
            'payment_method' => 'cash',
            'cash_received' => '100.00', // total is now 200; 100 < 200 → fails
        ],
    ])->assertSessionHasErrors(['cash_received']);
});

// ── KOT sync ─────────────────────────────────────────────────────────────────

it('updates KOT items when all are still pending', function () {
    $admin = userWith('Admin');
    $product = editProductWithStation();
    $sale = editCheckout($admin, $product);

    $kot = KitchenOrder::where('sale_id', $sale->id)->first();
    expect($kot)->not->toBeNull();
    expect($kot->items->every(fn ($i) => $i->status === KitchenStatus::Pending))->toBeTrue();

    $existingItem = $sale->items->first();

    editPatch($admin, $sale, [
        'items' => [
            ['existing_id' => $existingItem->id, 'quantity' => 2],
        ],
        'payment' => [
            'payment_method' => 'cash',
            'cash_received' => '200.00',
        ],
    ])->assertRedirect();

    // KOT items should be refreshed with the new quantity.
    $kot->refresh()->load('items');
    expect($kot->items->first()->quantity)->toBe(2);
});

it('leaves KOT unchanged and flashes a warning when items are already in progress', function () {
    $admin = userWith('Admin');
    $product = editProductWithStation();
    $sale = editCheckout($admin, $product);

    $kot = KitchenOrder::where('sale_id', $sale->id)->first();
    $originalItemCount = $kot->items()->count();
    $kot->items()->update(['status' => KitchenStatus::Preparing->value]);

    editPatch($admin, $sale, ['customer_name' => 'New Name'])
        ->assertRedirect()
        ->assertSessionHas('warning');

    // KOT items must not have been rebuilt — count and status stay the same.
    expect($kot->items()->count())->toBe($originalItemCount);
    expect($kot->items()->first()->status)->toBe(KitchenStatus::Preparing);
});

// ── Idempotency / no-op edit ──────────────────────────────────────────────────

it('a no-op edit still writes an audit row', function () {
    $admin = userWith('Admin');
    $product = editProduct();
    $sale = editCheckout($admin, $product);

    editPatch($admin, $sale)->assertRedirect();

    expect(SaleEdit::where('sale_id', $sale->id)->count())->toBe(1);
});
