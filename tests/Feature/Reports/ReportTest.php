<?php

use App\Models\Customer;
use App\Models\Expense;
use App\Models\ExpenseCategory;
use App\Models\Product;
use App\Models\Sale;
use App\Models\Supplier;
use App\Services\PurchaseService;
use App\Services\ReturnService;
use App\Services\SaleService;
use App\Support\DateRange;
use Carbon\CarbonImmutable;
use Database\Seeders\RolesAndPermissionsSeeder;

beforeEach(function () {
    $this->seed(RolesAndPermissionsSeeder::class);
    $this->travelTo(CarbonImmutable::parse('2026-09-24 12:00:00'));
    $this->actingAs($this->manager = userWith('Manager'));
});

function reportSale(Product $product, int $quantity, ?Customer $customer = null, string $method = 'cash'): Sale
{
    $payment = $method === 'card'
        ? ['payment_method' => 'card', 'card_amount' => bcmul((string) $product->selling_price, (string) $quantity, 2)]
        : ['payment_method' => 'cash', 'cash_received' => '100000'];

    return app(SaleService::class)->checkout(
        ['items' => [['product_id' => $product->id, 'quantity' => $quantity]], 'customer_id' => $customer?->id],
        $payment,
        test()->cashier,
    )->load('items');
}

/**
 * Today (2026-09-24):   sales 500 (Ali, cash) + 200 (card) = 700; Coke returned (refund 200);
 *                       purchases 1000 completed (+ 999 draft, excluded); expenses 150 (Rent).
 * Yesterday:            sale 300 (Fries); purchase 400; expense 50 (Internet).
 */
function seedReportData(): void
{
    $t = test();
    $t->cashier = userWith('Cashier');
    $t->ali = Customer::factory()->create(['name' => 'Ali']);
    $t->burger = Product::factory()->create(['name' => 'Burger', 'selling_price' => 500, 'tax_rate' => 0]);
    $t->coke = Product::factory()->create(['name' => 'Coke', 'selling_price' => 200, 'tax_rate' => 0]);
    $t->fries = Product::factory()->create(['name' => 'Fries', 'selling_price' => 300, 'tax_rate' => 0]);
    $supplier = Supplier::factory()->create(['name' => 'Metro']);
    $rent = ExpenseCategory::factory()->create(['name' => 'Rent']);
    $internet = ExpenseCategory::factory()->create(['name' => 'Internet']);
    $purchases = app(PurchaseService::class);
    $purchase = fn (string $date, string $status, string $price) => $purchases->create(
        ['supplier_id' => $supplier->id, 'purchase_date' => $date, 'payment_status' => 'paid', 'status' => $status],
        [['product_id' => $t->burger->id, 'quantity' => '1', 'purchase_price' => $price]],
        $t->manager,
    );

    $t->travelTo(CarbonImmutable::parse('2026-09-23 18:00:00'));
    reportSale($t->fries, 1);
    $t->travelTo(CarbonImmutable::parse('2026-09-24 12:00:00'));

    $t->aliSale = reportSale($t->burger, 1, $t->ali);
    $t->cokeSale = reportSale($t->coke, 1, method: 'card');
    app(ReturnService::class)->create($t->cokeSale, [
        'reason' => 'damaged', 'refund_method' => 'cash', 'status' => 'completed',
        'items' => [['sale_item_id' => $t->cokeSale->items->first()->id, 'quantity' => 1]],
    ], $t->manager);

    $purchase('2026-09-24', 'completed', '1000');
    $purchase('2026-09-24', 'draft', '999');
    $purchase('2026-09-23', 'completed', '400');

    Expense::factory()->create(['category_id' => $rent->id, 'amount' => 150, 'expense_date' => '2026-09-24']);
    Expense::factory()->create(['category_id' => $internet->id, 'amount' => 50, 'expense_date' => '2026-09-23']);
}

test('the report overview shows today by default from real data', function () {
    seedReportData();

    $this->get('/reports')
        ->assertOk()
        ->assertSee('Today · 24 Sep 2026')
        ->assertSeeInOrder(['data-summary="sales"', '$700', '2 sales'], false)
        ->assertSeeInOrder(['data-summary="returns"', '$200', '1 refund'], false)
        ->assertSeeInOrder(['data-summary="net"', '$500'], false)
        ->assertSeeInOrder(['data-summary="purchases"', '$1,000', '1 completed purchase'], false)
        ->assertSeeInOrder(['data-summary="expenses"', '$150', '1 expense'], false)
        ->assertSeeInOrder(['data-summary="customers"', '1', '1 on record'], false)
        ->assertSeeInOrder(['Sales by payment method', 'Cash', '$500', 'Card', '$200'])
        ->assertSeeInOrder(['Expenses by category', 'Rent', '$150']);
});

test('the overview follows the chosen period', function (array $query, array $expected) {
    seedReportData();
    $response = $this->get('/reports?'.http_build_query($query))->assertOk();

    foreach ($expected as $card => $amount) {
        $response->assertSeeInOrder(["data-summary=\"{$card}\"", $amount], false);
    }
})->with([
    'yesterday' => [['period' => 'yesterday'], ['sales' => '$300', 'returns' => '$0', 'purchases' => '$400', 'expenses' => '$50']],
    'this week' => [['period' => 'this_week'], ['sales' => '$1,000', 'net' => '$800', 'purchases' => '$1,400', 'expenses' => '$200']],
    'this month' => [['period' => 'this_month'], ['sales' => '$1,000', 'purchases' => '$1,400']],
    'last month' => [['period' => 'last_month'], ['sales' => '$0', 'purchases' => '$0', 'expenses' => '$0']],
    'custom' => [['period' => 'custom', 'from' => '2026-09-23', 'to' => '2026-09-23'], ['sales' => '$300', 'expenses' => '$50']],
]);

test('with no data every report shows zero or an empty state', function () {
    $this->get('/reports')->assertOk()->assertSeeInOrder(['data-summary="sales"', '$0', '0 sales'], false)->assertSee('No sales in this period.');
    $this->get('/reports/sales')->assertSee('No sales in this period');
    $this->get('/reports/purchases')->assertSee('No purchases in this period');
    $this->get('/reports/returns')->assertSee('No returns in this period');
    $this->get('/reports/expenses')->assertSee('No expenses in this period');
    $this->get('/reports/customers')->assertSee('No customer purchases in this period');
    $this->get('/reports/products')->assertSee('No products sold in this period');
});

test('the sales report lists sales with totals and filters', function () {
    seedReportData();

    $this->get('/reports/sales')
        ->assertOk()
        ->assertSee($this->aliSale->invoice_number)
        ->assertSee($this->cokeSale->invoice_number)
        ->assertSee('Ali')
        ->assertSee($this->cashier->name)
        ->assertSee('Partially returned')
        ->assertSeeInOrder(['data-totals', '2 sales', '$700'], false);

    $this->get("/reports/sales?customer={$this->ali->id}")->assertSee($this->aliSale->invoice_number)->assertDontSee($this->cokeSale->invoice_number);
    $this->get('/reports/sales?method=card')->assertSee($this->cokeSale->invoice_number)->assertDontSee($this->aliSale->invoice_number);
    $this->get('/reports/sales?returns=none')->assertSee($this->aliSale->invoice_number)->assertDontSee($this->cokeSale->invoice_number);
    $this->get("/reports/sales?cashier={$this->manager->id}")->assertSee('No sales in this period');
});

test('reports export to CSV with the same filters', function () {
    seedReportData();

    $response = $this->get('/reports/sales?export=csv&method=card')->assertOk()->assertHeader('content-type', 'text/csv; charset=UTF-8');
    $csv = $response->streamedContent();

    expect($csv)->toContain('Invoice,Date,Customer')
        ->toContain($this->cokeSale->invoice_number)
        ->not->toContain($this->aliSale->invoice_number);

    foreach (['purchases', 'returns', 'expenses', 'customers', 'products'] as $report) {
        expect($this->get("/reports/{$report}?export=csv&period=this_month")->assertOk()->streamedContent())->not->toBeEmpty();
    }
});

test('the purchase report counts completed purchases unless asked otherwise', function () {
    seedReportData();

    $this->get('/reports/purchases')->assertSeeInOrder(['data-totals', '1 purchase', '$1,000'], false)->assertDontSee('999.00');
    $this->get('/reports/purchases?status=all')->assertSeeInOrder(['data-totals', '2 purchases', '$1,999'], false);
    $this->get('/reports/purchases?status=draft')->assertSee('999')->assertDontSee('$1,000.00');
});

test('the returns and expense reports use their own dates and filters', function () {
    seedReportData();

    $this->get('/reports/returns')->assertSee('RET-000001')->assertSee($this->cokeSale->invoice_number)->assertSeeInOrder(['data-totals', '$200'], false);
    $this->get('/reports/returns?status=pending')->assertSee('No returns in this period');

    $this->get('/reports/expenses?period=this_month')->assertSeeInOrder(['data-totals', '2 expenses', '$200'], false);
    $this->get('/reports/expenses?period=this_month&category='.ExpenseCategory::where('name', 'Internet')->value('id'))
        ->assertSeeInOrder(['data-totals', '1 expense', '$50'], false);
});

test('the customer and product reports summarise real sales', function () {
    seedReportData();

    $this->get('/reports/customers')->assertOk()->assertSeeInOrder(['Ali', '1', '$500']);

    $this->get('/reports/products?period=this_month')
        ->assertOk()
        ->assertSeeInOrder(['Burger', '1', '0', '1', '$500'])
        ->assertSeeInOrder(['Coke', '1', '1', '1', '$200'])
        ->assertSee('Fries');
});

test('reports need reports.view', function (string $role, int $status) {
    $this->actingAs(userWith($role));

    foreach (['', '/sales', '/purchases', '/returns', '/expenses', '/customers', '/products'] as $path) {
        $this->get("/reports{$path}")->assertStatus($status);
    }
})->with([
    'admin' => ['Admin', 200],
    'manager' => ['Manager', 200],
    'cashier' => ['Cashier', 403],
    'kitchen' => ['Kitchen Staff', 403],
]);

test('report periods are validated', function () {
    $this->get('/reports?period=forever')->assertSessionHasErrors('period');
    $this->get('/reports?period=custom')->assertSessionHasErrors(['from', 'to']);
    $this->get('/reports?period=custom&from=2026-09-10&to=2026-09-01')->assertSessionHasErrors('to');
});

test('date range presets cover the right days', function () {
    $check = fn (string $preset) => [DateRange::preset($preset)->from->toDateTimeString(), DateRange::preset($preset)->to->toDateTimeString()];

    expect($check('today'))->toBe(['2026-09-24 00:00:00', '2026-09-24 23:59:59'])
        ->and($check('yesterday'))->toBe(['2026-09-23 00:00:00', '2026-09-23 23:59:59'])
        ->and($check('this_week'))->toBe(['2026-09-21 00:00:00', '2026-09-24 23:59:59'])
        ->and($check('this_month'))->toBe(['2026-09-01 00:00:00', '2026-09-24 23:59:59'])
        ->and($check('last_month'))->toBe(['2026-08-01 00:00:00', '2026-08-31 23:59:59']);
});

test('the dashboard shows real figures for today', function () {
    seedReportData();

    $this->actingAs(userWith('Admin'))
        ->get('/dashboard')
        ->assertOk()
        ->assertSeeInOrder(["Today's Sales", '$700', "Today's Orders", '2', "Today's Purchases", '$1,000', "Today's Returns", '$200', "Today's Expenses", '$150'])
        ->assertSee($this->aliSale->invoice_number)
        ->assertSee('Burger')
        ->assertDontSee('No sales recorded in this period');
});
