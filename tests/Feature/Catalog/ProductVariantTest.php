<?php

use App\Models\Product;
use App\Models\ProductVariant;
use Database\Seeders\RolesAndPermissionsSeeder;

beforeEach(function () {
    $this->seed(RolesAndPermissionsSeeder::class);
});

test('variant pages render', function () {
    $product = Product::factory()->create();
    $variant = ProductVariant::factory()->for($product)->create();
    $this->actingAs(userWith('Admin'));

    $this->get("/products/{$product->id}/variants/create")->assertOk()->assertDontSee('Opening stock');
    $this->get("/products/{$product->id}/variants/{$variant->id}/edit")->assertOk();
});

test('a variant can be added', function () {
    $product = Product::factory()->create(['sku' => 'TEE', 'selling_price' => 1000]);

    $this->actingAs(userWith('Admin'))
        ->post("/products/{$product->id}/variants", ['name' => 'Large', 'selling_price' => '1200', 'is_active' => '1'])
        ->assertSessionHasNoErrors()
        ->assertRedirect(route('products.show', $product));

    $variant = $product->variants()->sole();

    expect($variant->sku)->toBe('TEE-LARGE')
        ->and($variant->effectiveSellingPrice())->toBe(1200.0);
});

test('a variant can be added to an existing simple product', function () {
    // Regression: this used to be blocked when the product held stock.
    $zinger = Product::factory()->create(['name' => 'Zinger', 'sku' => 'ZINGER']);

    $this->actingAs(userWith('Super Admin'))
        ->post("/products/{$zinger->id}/variants", ['name' => 'Small Zinger', 'is_active' => '1'])
        ->assertSessionHasNoErrors();

    expect($zinger->fresh()->hasVariants())->toBeTrue()
        ->and($zinger->variants()->sole()->sku)->toBe('ZINGER-SMALL-ZINGER');
});

test('variants without their own price inherit the product price', function () {
    $product = Product::factory()->create(['selling_price' => 500, 'purchase_price' => 300]);
    $variant = ProductVariant::factory()->for($product)->create(['selling_price' => null, 'purchase_price' => null]);

    expect($variant->effectiveSellingPrice())->toBe(500.0)
        ->and($variant->effectivePurchasePrice())->toBe(300.0);
});

test('a product is not forced to have variants', function () {
    expect(Product::factory()->create()->hasVariants())->toBeFalse();
});

test('variant names are unique per product only', function () {
    $burger = Product::factory()->create();
    $pizza = Product::factory()->create();
    ProductVariant::factory()->for($burger)->create(['name' => 'Large']);
    $this->actingAs(userWith('Admin'));

    $this->post("/products/{$burger->id}/variants", ['name' => 'Large', 'is_active' => '1'])->assertSessionHasErrors('name');
    $this->post("/products/{$pizza->id}/variants", ['name' => 'Large', 'is_active' => '1'])->assertSessionHasNoErrors();
});

test('a deleted variant name can be reused', function () {
    $product = Product::factory()->create();
    ProductVariant::factory()->for($product)->create(['name' => 'Large'])->delete();

    $this->actingAs(userWith('Admin'))
        ->post("/products/{$product->id}/variants", ['name' => 'Large', 'is_active' => '1'])
        ->assertSessionHasNoErrors();
});

test('variant SKU and barcode must be unique across products and variants', function () {
    Product::factory()->create(['sku' => 'P-1', 'barcode' => '999']);
    $product = Product::factory()->create();
    $this->actingAs(userWith('Admin'));

    $this->post("/products/{$product->id}/variants", ['name' => 'A', 'sku' => 'P-1', 'is_active' => '1'])->assertSessionHasErrors('sku');
    $this->post("/products/{$product->id}/variants", ['name' => 'B', 'barcode' => '999', 'is_active' => '1'])->assertSessionHasErrors('barcode');
});

test('variant validation', function (array $data, string $field) {
    $product = Product::factory()->create();

    $this->actingAs(userWith('Admin'))
        ->post("/products/{$product->id}/variants", ['name' => 'Small', 'is_active' => '1', ...$data])
        ->assertSessionHasErrors($field);
})->with([
    'name required' => [['name' => ''], 'name'],
    'negative price' => [['selling_price' => '-1'], 'selling_price'],
    'negative cost' => [['purchase_price' => '-1'], 'purchase_price'],
]);

test('deleting a variant archives it', function () {
    $product = Product::factory()->create();
    $variant = ProductVariant::factory()->for($product)->create();

    $this->actingAs(userWith('Admin'))
        ->delete("/products/{$product->id}/variants/{$variant->id}")
        ->assertRedirect(route('products.show', $product));

    expect($variant->fresh()->trashed())->toBeTrue();
});

test('a variant is only reachable through its own product', function () {
    $variant = ProductVariant::factory()->create();
    $other = Product::factory()->create();

    $this->actingAs(userWith('Admin'))
        ->get("/products/{$other->id}/variants/{$variant->id}/edit")
        ->assertNotFound();
});

test('managing variants requires products.edit', function () {
    $product = Product::factory()->create();

    $this->actingAs(userWith('Cashier'))
        ->post("/products/{$product->id}/variants", ['name' => 'Small', 'is_active' => '1'])
        ->assertForbidden();
});
