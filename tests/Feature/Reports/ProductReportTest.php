<?php

use App\Models\Category;
use App\Models\Product;
use App\Services\DashboardService;
use App\Services\ReportService;
use App\Support\DateRange;
use Carbon\CarbonImmutable;
use Database\Seeders\RolesAndPermissionsSeeder;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\seed;

beforeEach(function () {
    seed(RolesAndPermissionsSeeder::class);
});

// ── Helpers ───────────────────────────────────────────────────────────────────

function productReportSale(\App\Models\User $user, array $items): \App\Models\Sale
{
    $response = actingAs($user)->postJson('/sales', array_merge([
        'order_type'     => 'take_away',
        'payment_method' => 'cash',
        'cash_received'  => '9999',
    ], ['items' => $items]));

    $response->assertCreated();

    return \App\Models\Sale::find($response->json('sale.id'));
}

// ── DashboardService::topSellingProducts ─────────────────────────────────────

it('top selling products includes returned quantity and net_qty', function () {
    $admin   = userWith('Admin');
    $product = Product::factory()->create(['selling_price' => '50.00', 'is_active' => true, 'tax_rate' => 0]);

    productReportSale($admin, [
        ['product_id' => $product->id, 'variant_id' => null, 'quantity' => 3, 'modifier_ids' => [], 'discount_type' => null, 'discount_value' => '0'],
    ]);

    $top = app(DashboardService::class)->topSellingProducts(5);

    expect($top->first())->toHaveKey('returned')
        ->and($top->first())->toHaveKey('net_qty')
        ->and($top->first()['quantity'])->toBe(3)
        ->and($top->first()['net_qty'])->toBe(3); // no returns
});

// ── ReportService::products with category filter ──────────────────────────────

it('product report filters by category', function () {
    $admin    = userWith('Admin');
    $cat1     = Category::factory()->create(['name' => 'Hot Food']);
    $cat2     = Category::factory()->create(['name' => 'Drinks']);
    $burger   = Product::factory()->for($cat1)->create(['selling_price' => '100.00', 'is_active' => true, 'tax_rate' => 0]);
    $juice    = Product::factory()->for($cat2)->create(['selling_price' => '50.00', 'is_active' => true, 'tax_rate' => 0]);

    productReportSale($admin, [
        ['product_id' => $burger->id, 'variant_id' => null, 'quantity' => 2, 'modifier_ids' => [], 'discount_type' => null, 'discount_value' => '0'],
        ['product_id' => $juice->id, 'variant_id' => null, 'quantity' => 1, 'modifier_ids' => [], 'discount_type' => null, 'discount_value' => '0'],
    ]);

    $service = app(ReportService::class);
    $range   = DateRange::preset('today');

    $all    = $service->products($range)->get();
    $hotOnly = $service->products($range, ['category' => $cat1->id])->get();

    expect($all)->toHaveCount(2)
        ->and($hotOnly)->toHaveCount(1)
        ->and($hotOnly->first()->product_name)->toBe($burger->name);
});

// ── ReportService::productTotals ─────────────────────────────────────────────

it('product totals sums quantity and revenue across all products', function () {
    $admin   = userWith('Admin');
    $cat     = Category::factory()->create();
    $burger  = Product::factory()->for($cat)->create(['selling_price' => '100.00', 'is_active' => true, 'tax_rate' => 0]);
    $fries   = Product::factory()->for($cat)->create(['selling_price' => '50.00', 'is_active' => true, 'tax_rate' => 0]);

    productReportSale($admin, [
        ['product_id' => $burger->id, 'variant_id' => null, 'quantity' => 2, 'modifier_ids' => [], 'discount_type' => null, 'discount_value' => '0'],
        ['product_id' => $fries->id, 'variant_id' => null, 'quantity' => 3, 'modifier_ids' => [], 'discount_type' => null, 'discount_value' => '0'],
    ]);

    $service = app(ReportService::class);
    $range   = DateRange::preset('today');
    $totals  = $service->productTotals($range);

    expect((int) $totals->qty)->toBe(5)
        ->and((float) $totals->revenue)->toBe(350.0); // 2×100 + 3×50
});

// ── GET /reports/products ──────────────────────────────────────────────────────

it('product report page shows a category filter and totals row', function () {
    $admin   = userWith('Admin');
    $cat     = Category::factory()->create(['name' => 'Starters']);
    $product = Product::factory()->for($cat)->create(['selling_price' => '80.00', 'is_active' => true, 'tax_rate' => 0]);

    productReportSale($admin, [
        ['product_id' => $product->id, 'variant_id' => null, 'quantity' => 2, 'modifier_ids' => [], 'discount_type' => null, 'discount_value' => '0'],
    ]);

    actingAs($admin)->get(route('reports.products', ['period' => 'today']))
        ->assertOk()
        ->assertSee('All categories')         // placeholder in select
        ->assertSee('data-totals-row', false); // totals row present
});

it('product report category filter narrows results', function () {
    $admin    = userWith('Admin');
    $cat1     = Category::factory()->create(['name' => 'Main']);
    $cat2     = Category::factory()->create(['name' => 'Sides']);
    $mainItem = Product::factory()->for($cat1)->create(['name' => 'Steak', 'selling_price' => '200.00', 'is_active' => true, 'tax_rate' => 0]);
    $sideItem = Product::factory()->for($cat2)->create(['name' => 'Salad', 'selling_price' => '50.00', 'is_active' => true, 'tax_rate' => 0]);

    productReportSale($admin, [
        ['product_id' => $mainItem->id, 'variant_id' => null, 'quantity' => 1, 'modifier_ids' => [], 'discount_type' => null, 'discount_value' => '0'],
        ['product_id' => $sideItem->id, 'variant_id' => null, 'quantity' => 1, 'modifier_ids' => [], 'discount_type' => null, 'discount_value' => '0'],
    ]);

    actingAs($admin)->get(route('reports.products', ['period' => 'today', 'category' => $cat1->id]))
        ->assertOk()
        ->assertSee('Steak')
        ->assertDontSee('Salad');
});

it('product report CSV includes net sold column', function () {
    $admin   = userWith('Admin');
    $product = Product::factory()->create(['selling_price' => '100.00', 'is_active' => true, 'tax_rate' => 0]);

    productReportSale($admin, [
        ['product_id' => $product->id, 'variant_id' => null, 'quantity' => 2, 'modifier_ids' => [], 'discount_type' => null, 'discount_value' => '0'],
    ]);

    $response = actingAs($admin)->get(route('reports.products', ['period' => 'today', 'export' => 'csv']));

    $response->assertOk()->assertHeader('Content-Type', 'text/csv; charset=UTF-8');
    expect($response->streamedContent())->toContain('Net sold');
});
