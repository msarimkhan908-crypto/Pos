<?php

use App\Models\Sale;
use App\Models\SaleReturn;
use App\Services\SaleService;
use App\Services\DashboardService;
use Carbon\CarbonImmutable;
use Database\Seeders\RolesAndPermissionsSeeder;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\seed;

beforeEach(function () {
    seed(RolesAndPermissionsSeeder::class);
});

// ── Helpers ───────────────────────────────────────────────────────────────────

function dailyProduct(): \App\Models\Product
{
    return \App\Models\Product::factory()->create([
        'selling_price' => '100.00',
        'is_active'     => true,
        'tax_rate'      => 0,
    ]);
}

function dailySale(\App\Models\User $user, \App\Models\Product $product): Sale
{
    $response = actingAs($user)->postJson('/sales', [
        'items'          => [
            ['product_id' => $product->id, 'variant_id' => null, 'quantity' => 1,
                'modifier_ids' => [], 'discount_type' => null, 'discount_value' => '0'],
        ],
        'order_type'     => 'take_away',
        'payment_method' => 'cash',
        'cash_received'  => '100.00',
    ]);
    $response->assertCreated();
    return Sale::find($response->json('sale.id'));
}

// ── DashboardService::dailyReport ─────────────────────────────────────────────

it('returns zero totals when there are no sales', function () {
    $report = app(DashboardService::class)->dailyReport(CarbonImmutable::today());

    expect($report['sales_count'])->toBe(0)
        ->and($report['sales_total'])->toBe(0.0)
        ->and($report['net_revenue'])->toBe(0.0)
        ->and($report['payments'])->toBe([]);
});

it('aggregates sales by business_date', function () {
    $admin   = userWith('Admin');
    $product = dailyProduct();

    dailySale($admin, $product);
    dailySale($admin, $product);

    $report = app(DashboardService::class)->dailyReport(CarbonImmutable::today());

    expect($report['sales_count'])->toBe(2)
        ->and($report['sales_total'])->toBe(200.0)
        ->and($report['avg_order'])->toBe(100.0);
});

it('breaks down payments by method', function () {
    $admin   = userWith('Admin');
    $product = dailyProduct();

    dailySale($admin, $product);

    $report = app(DashboardService::class)->dailyReport(CarbonImmutable::today());

    expect($report['payments'])->toHaveKey('cash')
        ->and((float) $report['payments']['cash'])->toBe(100.0);
});

it('subtracts returns from net revenue', function () {
    $admin   = userWith('Admin');
    $product = dailyProduct();
    $sale    = dailySale($admin, $product);

    // Create a return record against the sale.
    \App\Models\SaleReturn::forceCreate([
        'return_number'  => 'RET-000001',
        'sale_id'        => $sale->id,
        'return_date'    => now()->toDateString(),
        'reason'         => \App\Enums\ReturnReason::Other,
        'refund_method'  => \App\Enums\MoneyMethod::Cash,
        'refund_amount'  => '50.00',
        'status'         => \App\Enums\ReturnStatus::Completed,
    ]);

    $report = app(DashboardService::class)->dailyReport(CarbonImmutable::today());

    expect($report['returns_total'])->toBe(50.0)
        ->and($report['net_revenue'])->toBe(50.0); // 100 - 50
});

// ── GET /reports/daily ─────────────────────────────────────────────────────────

it('admin can view the daily report page', function () {
    $admin = userWith('Admin');
    actingAs($admin)->get(route('reports.daily'))->assertOk()->assertSee('Daily Report');
});

it('cashier without reports.view cannot view the daily report page', function () {
    $cashier = userWith('Cashier');
    actingAs($cashier)->get(route('reports.daily'))->assertForbidden();
});

it('daily report page shows figures for the requested date', function () {
    $admin   = userWith('Admin');
    $product = dailyProduct();

    dailySale($admin, $product);
    dailySale($admin, $product);

    actingAs($admin)->get(route('reports.daily', ['date' => today()->toDateString()]))
        ->assertOk()
        ->assertSee('Daily Report')
        ->assertSee('data-daily-report', false);
});

it('daily report defaults to today when no date is given', function () {
    $admin = userWith('Admin');
    actingAs($admin)->get(route('reports.daily'))
        ->assertOk()
        ->assertSee(today()->isoFormat('D MMMM YYYY'));
});

it('daily report CSV export streams a file', function () {
    $admin   = userWith('Admin');
    $product = dailyProduct();

    dailySale($admin, $product);

    actingAs($admin)->get(route('reports.daily', ['date' => today()->toDateString(), 'export' => 'csv']))
        ->assertOk()
        ->assertHeader('Content-Type', 'text/csv; charset=UTF-8');
});

// ── Dashboard widget ──────────────────────────────────────────────────────────

it('daily_report widget appears on the dashboard when enabled', function () {
    $admin   = userWith('Admin');
    $product = dailyProduct();

    dailySale($admin, $product);

    // Ensure daily_report widget is enabled.
    settings()->set(['dashboard.widgets' => \App\Settings\SettingDefinitions::DASHBOARD_WIDGETS]);

    actingAs($admin)->get(route('dashboard'))
        ->assertOk()
        ->assertSee('data-daily-report', false);
});

it('daily_report widget is hidden when switched off', function () {
    $admin = userWith('Admin');

    settings()->set(['dashboard.widgets' => ['sales']]);

    actingAs($admin)->get(route('dashboard'))
        ->assertOk()
        ->assertDontSee('data-daily-report', false);
});
