<?php

use App\Models\KitchenStation;
use App\Models\Product;
use App\Models\RestaurantTable;
use App\Services\SaleService;
use App\Support\Money;
use Database\Seeders\RolesAndPermissionsSeeder;

beforeEach(function () {
    $this->seed(RolesAndPermissionsSeeder::class);
    // Start each test with the default: hide_trailing_zeros = true
    settings()->set(['general.hide_trailing_zeros' => true]);
});

// ------------------------------------------------------------------ number()

test('Money::number() hides .00 on whole amounts', function () {
    expect(Money::number('350.00'))->toBe('350')
        ->and(Money::number('1250.00'))->toBe('1,250')
        ->and(Money::number(0))->toBe('0');
});

test('Money::number() keeps 2 decimals on fractional amounts', function () {
    expect(Money::number('350.50'))->toBe('350.50')
        ->and(Money::number('1250.50'))->toBe('1,250.50');
});

test('Money::number() preserves negative sign', function () {
    expect(Money::number('-350.00'))->toBe('-350')
        ->and(Money::number('-350.50'))->toBe('-350.50');
});

test('Money::number() shows 2 decimals on all amounts when hide_trailing_zeros is off', function () {
    settings()->set(['general.hide_trailing_zeros' => false]);

    expect(Money::number('350.00'))->toBe('350.00')
        ->and(Money::number('1250.00'))->toBe('1,250.00')
        ->and(Money::number('350.50'))->toBe('350.50');
});

// ------------------------------------------------------------------ format()

test('Money::format() with currency symbol hides .00 on whole amounts', function () {
    settings()->set(['general.currency_symbol' => 'Rs', 'general.hide_trailing_zeros' => true]);

    expect(Money::format(350))->toBe('Rs 350')
        ->and(Money::format(1250))->toBe('Rs 1,250')
        ->and(Money::format(350.50))->toBe('Rs 350.50')
        ->and(Money::format(1250.50))->toBe('Rs 1,250.50')
        ->and(Money::format(0))->toBe('Rs 0')
        ->and(Money::format(-350))->toBe('-Rs 350');
});

test('Money::format() with currency symbol shows 2 decimals when setting is off', function () {
    settings()->set(['general.currency_symbol' => 'Rs', 'general.hide_trailing_zeros' => false]);

    expect(Money::format(350))->toBe('Rs 350.00')
        ->and(Money::format(1250.50))->toBe('Rs 1,250.50');
});

// ------------------------------------------------------------------ plain()

test('Money::plain() always outputs exactly 2 decimals with no thousands separator', function () {
    expect(Money::plain('350.00'))->toBe('350.00')
        ->and(Money::plain('1250.50'))->toBe('1250.50')
        ->and(Money::plain(350))->toBe('350.00')
        ->and(Money::plain(0))->toBe('0.00');
});

test('Money::plain() is unaffected by the hide_trailing_zeros setting', function () {
    settings()->set(['general.hide_trailing_zeros' => true]);

    expect(Money::plain('350.00'))->toBe('350.00')
        ->and(Money::plain('1000.00'))->toBe('1000.00');
});

// ------------------------------------------------------------------ receipt HTML

test('receipt HTML shows whole amounts without .00 when hide_trailing_zeros is on', function () {
    $this->seed(RolesAndPermissionsSeeder::class);
    $this->actingAs(userWith('Admin'));

    $grill = KitchenStation::factory()->create(['name' => 'Grill']);
    $burger = Product::factory()->create([
        'name' => 'Burger', 'selling_price' => 500, 'tax_rate' => 0,
        'kitchen_station_id' => $grill->id,
    ]);
    $table = RestaurantTable::factory()->create(['name' => 'Table 1']);

    $sale = app(SaleService::class)->checkout([
        'order_type' => 'dine_in',
        'table_id' => $table->id,
        'items' => [['product_id' => $burger->id, 'quantity' => 2]],
    ], ['payment_method' => 'cash', 'cash_received' => '2000'], userWith('Cashier'));

    settings()->set(['general.currency_symbol' => 'Rs', 'general.hide_trailing_zeros' => true]);

    // Total = 2 × 500 = 1000 (whole number)
    $this->get("/sales/{$sale->id}/receipt")
        ->assertSee('Rs 1,000')
        ->assertDontSee('Rs 1,000.00');
});

test('Money::format() without symbol strips .00 from Number::currency output', function () {
    settings()->set(['general.currency_symbol' => null, 'general.hide_trailing_zeros' => true, 'general.currency' => 'USD']);

    expect(Money::format(700))->toBe('$700')
        ->and(Money::format(700.50))->toBe('$700.50');
});

// ------------------------------------------------------------------ hide_trailing_zeros setting is saved

test('hide_trailing_zeros setting is saved and read back correctly', function () {
    $this->actingAs(userWith('Admin'));

    $this->put('/settings/general', [
        'business_day_start' => '00:00', 'currency' => 'PKR', 'currency_symbol' => 'Rs',
        'date_format' => 'd M Y', 'time_format' => 'H:i', 'timezone' => 'UTC',
        'invoice_prefix' => 'INV-', 'purchase_prefix' => 'PO-', 'return_prefix' => 'RET-',
        'expense_prefix' => 'EXP-', 'kot_prefix' => 'KOT-',
        'hide_trailing_zeros' => '1',
    ])->assertRedirect();

    expect(settings('general.hide_trailing_zeros'))->toBeTrue();

    $this->put('/settings/general', [
        'business_day_start' => '00:00', 'currency' => 'PKR', 'currency_symbol' => '',
        'date_format' => 'd M Y', 'time_format' => 'H:i', 'timezone' => 'UTC',
        'invoice_prefix' => 'INV-', 'purchase_prefix' => 'PO-', 'return_prefix' => 'RET-',
        'expense_prefix' => 'EXP-', 'kot_prefix' => 'KOT-',
        'hide_trailing_zeros' => '0',
    ])->assertRedirect();

    expect(settings('general.hide_trailing_zeros'))->toBeFalse();
});
