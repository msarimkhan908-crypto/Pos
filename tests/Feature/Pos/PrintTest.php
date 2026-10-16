<?php

use App\Enums\SaleStatus;
use App\Models\KitchenStation;
use App\Models\Product;
use App\Models\RestaurantTable;
use App\Models\Sale;
use Database\Seeders\RolesAndPermissionsSeeder;

beforeEach(function () {
    $this->seed(RolesAndPermissionsSeeder::class);

    $grill = KitchenStation::factory()->create(['name' => 'Grill']);
    $this->burger = Product::factory()->create(['name' => 'Chicken Burger', 'selling_price' => 650, 'kitchen_station_id' => $grill->id]);
    $this->giftCard = Product::factory()->create(['name' => 'Gift Card', 'selling_price' => 1000]);
    $this->table = RestaurantTable::factory()->create(['name' => 'Table 12']);

    $this->actingAs(userWith('Cashier'));
});

/**
 * @param  list<array<string, mixed>>  $items
 */
function placeOrder(array $items, array $overrides = []): Sale
{
    test()->postJson('/sales', [
        'order_type' => 'dine_in',
        'table_id' => test()->table->id,
        'items' => $items,
        'payment_method' => 'cash',
        'cash_received' => '5000',
        ...$overrides,
    ])->assertCreated()->assertJsonPath('print_url', fn (string $url) => str_ends_with($url, '/print'));

    return Sale::latest('id')->first();
}

test('a placed order prints the customer bill and the kitchen bill as separate pages', function () {
    $sale = placeOrder([['product_id' => $this->burger->id, 'quantity' => 2, 'note' => 'No onions']]);

    $response = $this->get("/sales/{$sale->id}/print")->assertOk();

    // Customer bill first, then the KOT; each is its own slip (page break between them).
    $response->assertSeeInOrder([
        'data-slip="customer"', $sale->invoice_number, 'Chicken Burger', 'Total Price',
        'data-slip="kitchen"', 'KOT (Kitchen)', 'KOT-1', 'TABLE', 'Table 12', 'Grill', '2 x Chicken Burger', 'No onions',
    ], false);

    // Each slip is a .print-sheet; app.css starts every sheet after the first on a new page.
    expect(substr_count($response->getContent(), 'class="print-sheet '))->toBe(2)
        ->and(file_get_contents(resource_path('css/app.css')))->toContain('.print-sheet + .print-sheet { break-before: page; page-break-before: always; }');
});

test('each bill can be reprinted on its own', function () {
    $sale = placeOrder([['product_id' => $this->burger->id, 'quantity' => 1]]);

    $this->get("/sales/{$sale->id}/print?only=receipt")
        ->assertOk()->assertSee('data-slip="customer"', false)->assertDontSee('data-slip="kitchen"', false);

    $this->get("/sales/{$sale->id}/print?only=kitchen")
        ->assertOk()->assertSee('data-slip="kitchen"', false)->assertDontSee('data-slip="customer"', false)->assertSee('KOT-1');
});

test('an order with no kitchen items prints only the customer bill', function () {
    $sale = placeOrder([['product_id' => $this->giftCard->id, 'quantity' => 1]], ['order_type' => 'take_away', 'table_id' => null]);

    $content = $this->get("/sales/{$sale->id}/print")->assertOk()->getContent();

    expect(substr_count($content, 'data-slip="customer"'))->toBe(1)
        ->and($content)->not->toContain('data-slip="kitchen"');
});

test('the print page only exists for completed sales and needs sales.view', function () {
    $held = Sale::forceCreate(['status' => SaleStatus::Held]);
    $this->get("/sales/{$held->id}/print")->assertNotFound();

    $sale = placeOrder([['product_id' => $this->burger->id, 'quantity' => 1]]);
    $this->actingAs(userWith('Kitchen Staff'))->get("/sales/{$sale->id}/print")->assertForbidden();
});

test('the POS prints on checkout as set in POS Settings', function () {
    // @js() writes the config inside JSON.parse('...'), where quotes are encoded as backslash-u0022.
    $q = chr(92).'u0022';
    $printConfig = fn (string $receipt, string $kot, string $layout) => "print{$q}:{{$q}receipt{$q}:{$receipt},{$q}kot{$q}:{$kot},{$q}layout{$q}:{$q}{$layout}{$q}}";

    $this->get('/pos')->assertOk()->assertSee('data-print-frame', false)->assertSee($printConfig('true', 'true', 'combined'), false);

    settings()->set(['print.auto_receipt' => false, 'print.auto_kot' => true, 'print.layout' => 'separate']);
    $this->get('/pos')->assertSee($printConfig('false', 'true', 'separate'), false);
});
