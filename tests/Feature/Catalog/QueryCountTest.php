<?php

use App\Models\Brand;
use App\Models\Category;
use App\Models\Customer;
use App\Models\ModifierGroup;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\Supplier;
use App\Services\PurchaseService;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Support\Facades\DB;

/**
 * Guards against N+1 queries: a list page must run the same number of queries
 * whether it shows 2 rows or 12.
 */
beforeEach(function () {
    $this->seed(RolesAndPermissionsSeeder::class);
    $this->actingAs(userWith('Super Admin'));
});

function seedCatalogRows(int $count): void
{
    $group = ModifierGroup::factory()->hasModifiers(3)->create();
    $service = app(PurchaseService::class);

    for ($i = 0; $i < $count; $i++) {
        $product = Product::factory()->for(Category::factory())->for(Brand::factory())->create();
        $product->modifierGroups()->attach($group);
        ProductVariant::factory()->for($product)->create(['name' => "V{$i}"]);

        $supplier = Supplier::factory()->create();
        Customer::factory()->create();
        $service->create(
            ['supplier_id' => $supplier->id, 'purchase_date' => now()->toDateString(), 'payment_status' => 'unpaid', 'status' => 'completed'],
            [['product_id' => $product->id, 'variant_id' => $product->variants()->value('id'), 'quantity' => '1', 'purchase_price' => '10']],
            userWith('Admin'),
        );
    }
}

function queriesFor(string $uri): int
{
    // Warm-up request: the first hit loads the permission cache, which is not per-row work.
    test()->get($uri)->assertOk();

    DB::flushQueryLog();
    DB::enableQueryLog();
    test()->get($uri)->assertOk();
    $count = count(DB::getQueryLog());
    DB::disableQueryLog();

    return $count;
}

test('list pages do not run a query per row', function (string $uri) {
    seedCatalogRows(2);
    $few = queriesFor($uri);

    seedCatalogRows(10);
    $many = queriesFor($uri);

    expect($many)->toBe($few);
})->with([
    '/products',
    '/categories',
    '/brands',
    '/modifier-groups',
    '/suppliers',
    '/purchases',
    '/customers',
    '/pos',
    '/pos/products',
]);

test('the purchase and supplier pages do not run a query per item', function () {
    $supplier = Supplier::factory()->create();
    $service = app(PurchaseService::class);

    $makePurchase = function (int $items) use ($supplier, $service) {
        $rows = collect(range(1, $items))->map(fn () => [
            'product_id' => Product::factory()->create()->id,
            'quantity' => '1',
            'purchase_price' => '5',
        ])->all();

        return $service->create(['supplier_id' => $supplier->id, 'purchase_date' => now()->toDateString(), 'payment_status' => 'unpaid', 'status' => 'completed'], $rows, userWith('Admin'));
    };

    $few = queriesFor('/purchases/'.$makePurchase(2)->id);
    $many = queriesFor('/purchases/'.$makePurchase(12)->id);
    expect($many)->toBe($few);

    $fewSupplier = queriesFor("/suppliers/{$supplier->id}");
    $makePurchase(1);
    $makePurchase(1);
    expect(queriesFor("/suppliers/{$supplier->id}"))->toBe($fewSupplier);
});

test('the product page does not run a query per variant', function () {
    $product = Product::factory()->create();
    $product->modifierGroups()->attach(ModifierGroup::factory()->hasModifiers(3)->create());

    $addVariants = function (int $count) use ($product) {
        foreach (range(1, $count) as $i) {
            ProductVariant::factory()->for($product)->create(['name' => uniqid("V{$i}-")]);
        }
    };

    $addVariants(2);
    $few = queriesFor("/products/{$product->id}");

    $addVariants(8);
    $many = queriesFor("/products/{$product->id}");

    expect($many)->toBe($few);
});
