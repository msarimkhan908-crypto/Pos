<?php

use App\Models\Product;
use App\Models\ProductVariant;
use Database\Seeders\RolesAndPermissionsSeeder;

beforeEach(function () {
    $this->seed(RolesAndPermissionsSeeder::class);
    $this->actingAs(userWith('Admin'));
});

/**
 * @param  array<string, mixed>  $overrides
 * @return array<string, mixed>
 */
function variableProduct(array $overrides = []): array
{
    return [
        'product_type' => 'variable',
        'name' => 'Pizza',
        'sku' => 'PIZZA',
        'selling_price' => '1000',
        'purchase_price' => '500',
        'is_active' => '1',
        'variants' => [
            ['name' => 'Small', 'selling_price' => '800', 'is_active' => '1'],
            ['name' => 'Medium', 'selling_price' => '', 'is_active' => '1'],
            ['name' => 'Large', 'selling_price' => '1400', 'sku' => 'PZ-L', 'barcode' => '4001', 'is_active' => '0'],
        ],
        ...$overrides,
    ];
}

test('the product form offers simple and variant types', function () {
    $this->get('/products/create')
        ->assertOk()
        ->assertSee('Simple product')
        ->assertSee('Variant product')
        ->assertSee('value="simple"', false)
        ->assertSee('value="variable"', false);
});

test('a simple product is the default type', function () {
    $this->post('/products', ['name' => 'Cola', 'selling_price' => '100', 'is_active' => '1'])
        ->assertSessionHasNoErrors();

    expect(Product::sole()->hasVariants())->toBeFalse();
});

test('a variant product is created with its variants in one save', function () {
    $this->post('/products', variableProduct())->assertSessionHasNoErrors()->assertRedirect();

    $product = Product::with('variants')->sole();
    [$small, $medium, $large] = $product->variants->all();

    expect($product->variants->pluck('name')->all())->toBe(['Small', 'Medium', 'Large'])
        ->and($small->sku)->toBe('PIZZA-SMALL')
        ->and($small->effectiveSellingPrice())->toBe(800.0)
        ->and($medium->selling_price)->toBeNull()
        ->and($medium->effectiveSellingPrice())->toBe(1000.0)
        ->and($large->sku)->toBe('PZ-L')
        ->and($large->barcode)->toBe('4001')
        ->and($large->is_active)->toBeFalse();
});

test('variant product validation', function (array $overrides, string $field) {
    $this->post('/products', variableProduct($overrides))->assertSessionHasErrors($field);

    expect(Product::count())->toBe(0);
})->with([
    'needs at least one variant' => [['variants' => []], 'variants'],
    'variant name required' => [['variants' => [['name' => '', 'is_active' => '1']]], 'variants.0.name'],
    'variant names distinct' => [['variants' => [['name' => 'Large', 'is_active' => '1'], ['name' => 'large', 'is_active' => '1']]], 'variants.1.name'],
    'variant SKUs distinct in form' => [['variants' => [['name' => 'A', 'sku' => 'X1', 'is_active' => '1'], ['name' => 'B', 'sku' => 'x1', 'is_active' => '1']]], 'variants.1.sku'],
    'variant SKU differs from product SKU' => [['variants' => [['name' => 'A', 'sku' => 'PIZZA', 'is_active' => '1']]], 'variants.0.sku'],
    'variant barcodes distinct in form' => [['variants' => [['name' => 'A', 'barcode' => '9', 'is_active' => '1'], ['name' => 'B', 'barcode' => '9', 'is_active' => '1']]], 'variants.1.barcode'],
    'negative variant price' => [['variants' => [['name' => 'A', 'selling_price' => '-1', 'is_active' => '1']]], 'variants.0.selling_price'],
    'unknown type' => [['product_type' => 'bundle'], 'product_type'],
]);

test('variant codes must not clash with existing products', function () {
    Product::factory()->create(['sku' => 'TAKEN', 'barcode' => '5555']);

    $this->post('/products', variableProduct(['variants' => [['name' => 'A', 'sku' => 'TAKEN', 'is_active' => '1']]]))->assertSessionHasErrors('variants.0.sku');
    $this->post('/products', variableProduct(['variants' => [['name' => 'A', 'barcode' => '5555', 'is_active' => '1']]]))->assertSessionHasErrors('variants.0.barcode');
});

test('editing a variant product updates, adds and removes variants', function () {
    $this->post('/products', variableProduct());
    $product = Product::with('variants')->sole();
    [$small, $medium, $large] = $product->variants->all();

    $this->get("/products/{$product->id}/edit")->assertOk()->assertSee('Small');

    $this->put("/products/{$product->id}", variableProduct([
        'variants' => [
            ['id' => $small->id, 'name' => 'Small (8")', 'selling_price' => '850', 'is_active' => '1'],
            ['id' => $medium->id, 'name' => 'Medium', 'is_active' => '1'],
            ['name' => 'Family', 'selling_price' => '2000', 'is_active' => '1'],
        ],
    ]))->assertSessionHasNoErrors();

    $variants = $product->fresh()->variants;

    expect($variants->pluck('name')->all())->toBe(['Small (8")', 'Medium', 'Family'])
        ->and($variants[0]->id)->toBe($small->id)
        ->and((float) $variants[0]->selling_price)->toBe(850.0)
        ->and(ProductVariant::withTrashed()->find($large->id)->trashed())->toBeTrue();
});

test('variants of another product cannot be edited through this product', function () {
    $foreign = ProductVariant::factory()->create();
    $this->post('/products', variableProduct());
    $product = Product::where('name', 'Pizza')->sole();

    $this->put("/products/{$product->id}", variableProduct([
        'variants' => [['id' => $foreign->id, 'name' => 'Hijack', 'is_active' => '1']],
    ]))->assertSessionHasErrors('variants.0.id');
});

test('a simple product can become a variant product', function () {
    $product = Product::factory()->create(['name' => 'Tee', 'sku' => 'TEE']);

    $this->put("/products/{$product->id}", variableProduct(['name' => 'Tee', 'sku' => 'TEE', 'variants' => [
        ['name' => 'M', 'is_active' => '1'],
        ['name' => 'L', 'is_active' => '1'],
    ]]))->assertSessionHasNoErrors();

    expect($product->fresh()->variants->pluck('name')->all())->toBe(['M', 'L']);
});

test('a variant product can become simple', function () {
    $this->post('/products', variableProduct());
    $product = Product::sole();

    $this->put("/products/{$product->id}", ['product_type' => 'simple', 'name' => 'Pizza', 'sku' => 'PIZZA', 'selling_price' => '1000', 'is_active' => '1'])
        ->assertSessionHasNoErrors();

    expect($product->fresh()->hasVariants())->toBeFalse()
        ->and(ProductVariant::onlyTrashed()->count())->toBe(3);
});

test('two variants can swap SKUs and barcodes in one save', function () {
    $this->post('/products', variableProduct(['variants' => [
        ['name' => 'A', 'sku' => 'CODE-1', 'barcode' => '111', 'is_active' => '1'],
        ['name' => 'B', 'sku' => 'CODE-2', 'barcode' => '222', 'is_active' => '1'],
    ]]));
    $product = Product::with('variants')->sole();
    [$a, $b] = $product->variants->all();

    $this->put("/products/{$product->id}", variableProduct(['variants' => [
        ['id' => $a->id, 'name' => 'A', 'sku' => 'CODE-2', 'barcode' => '222', 'is_active' => '1'],
        ['id' => $b->id, 'name' => 'B', 'sku' => 'CODE-1', 'barcode' => '111', 'is_active' => '1'],
    ]]))->assertSessionHasNoErrors();

    expect($a->fresh()->sku)->toBe('CODE-2')->and($b->fresh()->sku)->toBe('CODE-1')
        ->and($a->fresh()->barcode)->toBe('222')->and($b->fresh()->barcode)->toBe('111');
});
