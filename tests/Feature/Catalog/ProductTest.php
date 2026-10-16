<?php

use App\Models\Brand;
use App\Models\Category;
use App\Models\ModifierGroup;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Rules\ImageUpload;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

beforeEach(function () {
    $this->seed(RolesAndPermissionsSeeder::class);
    Storage::fake('public');
});

/**
 * @param  array<string, mixed>  $overrides
 * @return array<string, mixed>
 */
function productPayload(array $overrides = []): array
{
    return [
        'name' => 'Chicken Burger',
        'selling_price' => '450',
        'purchase_price' => '250',
        'tax_rate' => '0',
        'is_active' => '1',
        ...$overrides,
    ];
}

/**
 * A genuine upload whose MIME type is detected from its bytes (fakes use the filename).
 */
function realUpload(string $clientName, string $contents): UploadedFile
{
    $path = tempnam(sys_get_temp_dir(), 'upl');
    file_put_contents($path, $contents);

    return new UploadedFile($path, $clientName, null, null, true);
}

test('product pages render', function () {
    $product = Product::factory()->for(Category::factory())->for(Brand::factory())->create(['name' => 'Latte']);
    ProductVariant::factory()->for($product)->create(['name' => 'Large']);
    $product->modifierGroups()->attach(ModifierGroup::factory()->hasModifiers(2)->create());
    $this->actingAs(userWith('Admin'));

    $this->get('/products')->assertOk()->assertSee('Latte');
    $this->get('/products/create')->assertOk();
    $this->get("/products/{$product->id}")->assertOk()->assertSee('Large');
    $this->get("/products/{$product->id}/edit")->assertOk();
});

test('product pages show no stock or inventory controls', function () {
    $product = Product::factory()->create();
    $this->actingAs(userWith('Super Admin'));

    foreach (['/products', '/products/create', "/products/{$product->id}", "/products/{$product->id}/edit"] as $uri) {
        $this->get($uri)
            ->assertOk()
            ->assertDontSee('Adjust stock')
            ->assertDontSee('Opening stock')
            ->assertDontSee('Track inventory')
            ->assertDontSee('/inventory', false);
    }
});

test('a product can be created with category, brand, image and discount', function () {
    $category = Category::factory()->create();
    $brand = Brand::factory()->create();

    $this->actingAs(userWith('Admin'))
        ->post('/products', productPayload([
            'category_id' => $category->id,
            'brand_id' => $brand->id,
            'barcode' => '8964000123456',
            'discount_type' => 'percent',
            'discount_value' => '10',
            'image' => UploadedFile::fake()->image('burger.jpg', 600, 600),
        ]))
        ->assertSessionHasNoErrors()
        ->assertRedirect();

    $product = Product::firstWhere('name', 'Chicken Burger');

    expect($product->category->is($category))->toBeTrue()
        ->and($product->brand->is($brand))->toBeTrue()
        ->and($product->sku)->toStartWith('SKU-')
        ->and($product->discountedPrice())->toBe(405.0)
        ->and($product->image_path)->toStartWith('products/');

    Storage::disk('public')->assertExists($product->image_path);
});

test('product validation', function (array $data, string $field) {
    $this->actingAs(userWith('Admin'))
        ->post('/products', productPayload($data))
        ->assertSessionHasErrors($field);

    expect(Product::count())->toBe(0);
})->with([
    'name required' => [['name' => ''], 'name'],
    'selling price required' => [['selling_price' => ''], 'selling_price'],
    'selling price numeric' => [['selling_price' => 'abc'], 'selling_price'],
    'negative price' => [['selling_price' => '-1'], 'selling_price'],
    'three decimals' => [['selling_price' => '1.999'], 'selling_price'],
    'negative cost' => [['purchase_price' => '-5'], 'purchase_price'],
    'tax over 100' => [['tax_rate' => '101'], 'tax_rate'],
    'unknown category' => [['category_id' => 999], 'category_id'],
    'unknown brand' => [['brand_id' => 999], 'brand_id'],
    'percent discount over 100' => [['discount_type' => 'percent', 'discount_value' => '150'], 'discount_value'],
    'fixed discount over price' => [['discount_type' => 'fixed', 'discount_value' => '500'], 'discount_value'],
    'invalid discount type' => [['discount_type' => 'bogus', 'discount_value' => '1'], 'discount_type'],
    'invalid sku characters' => [['sku' => 'ABC 123'], 'sku'],
]);

test('SKUs must be unique across products and variants', function () {
    Product::factory()->create(['sku' => 'BURGER-01']);
    ProductVariant::factory()->create(['sku' => 'SHIRT-L']);
    $this->actingAs(userWith('Admin'));

    $this->post('/products', productPayload(['sku' => 'BURGER-01']))->assertSessionHasErrors('sku');
    $this->post('/products', productPayload(['sku' => 'SHIRT-L']))->assertSessionHasErrors('sku');
    $this->post('/products', productPayload(['sku' => 'BURGER-02']))->assertSessionHasNoErrors();
});

test('a product keeps its own SKU on update', function () {
    $product = Product::factory()->create(['sku' => 'KEEP-1', 'name' => 'Old']);

    $this->actingAs(userWith('Admin'))
        ->put("/products/{$product->id}", productPayload(['name' => 'New', 'sku' => 'KEEP-1']))
        ->assertSessionHasNoErrors();

    expect($product->fresh()->name)->toBe('New')->and($product->fresh()->sku)->toBe('KEEP-1');
});

test('SKUs of deleted products stay reserved', function () {
    Product::factory()->create(['sku' => 'OLD-1'])->delete();

    $this->actingAs(userWith('Admin'))
        ->post('/products', productPayload(['sku' => 'OLD-1']))
        ->assertSessionHasErrors('sku');
});

test('barcodes are optional but unique when set', function () {
    ProductVariant::factory()->create(['barcode' => '111']);
    $this->actingAs(userWith('Admin'));

    // Several products without a barcode are fine.
    $this->post('/products', productPayload(['name' => 'A', 'barcode' => '']))->assertSessionHasNoErrors();
    $this->post('/products', productPayload(['name' => 'B']))->assertSessionHasNoErrors();
    expect(Product::whereIn('name', ['A', 'B'])->whereNull('barcode')->count())->toBe(2);

    $this->post('/products', productPayload(['name' => 'C', 'barcode' => '222']))->assertSessionHasNoErrors();
    $this->post('/products', productPayload(['name' => 'D', 'barcode' => '222']))->assertSessionHasErrors('barcode');
    $this->post('/products', productPayload(['name' => 'E', 'barcode' => '111']))->assertSessionHasErrors('barcode');
});

test('real uploads are accepted only when the content is an image', function () {
    $png = tempnam(sys_get_temp_dir(), 'img');
    imagepng(imagecreatetruecolor(10, 10), $png);

    $this->actingAs(userWith('Admin'))
        ->post('/products', productPayload(['image' => new UploadedFile($png, 'photo.png', null, null, true)]))
        ->assertSessionHasNoErrors();

    // Stored with an extension derived from the detected type, not the client filename.
    expect(Product::sole()->image_path)->toEndWith('.png');
});

test('product image upload is validated by content, type and size', function (UploadedFile $file) {
    $this->actingAs(userWith('Admin'))
        ->post('/products', productPayload(['image' => $file]))
        ->assertSessionHasErrors('image');

    expect(Product::count())->toBe(0);
})->with([
    // A real UploadedFile (not a fake) so the MIME type is sniffed from the content, as in production.
    'text file renamed to .jpg' => fn () => realUpload('photo.jpg', 'this is not an image'),
    'php script renamed to .png' => fn () => realUpload('shell.png', '<?php echo "pwned"; ?>'),
    'pdf' => fn () => UploadedFile::fake()->create('photo.pdf', 50, 'application/pdf'),
    'svg not allowed' => fn () => UploadedFile::fake()->createWithContent('photo.svg', '<svg xmlns="http://www.w3.org/2000/svg"></svg>'),
    'over the limit' => fn () => UploadedFile::fake()->image('big.jpg')->size(ImageUpload::maxKilobytes() + 500),
]);

test('an image rejected by PHP for size gets a helpful message', function () {
    // What PHP reports when a file exceeds upload_max_filesize.
    $path = tempnam(sys_get_temp_dir(), 'upl');
    $tooBig = new UploadedFile($path, 'holiday.jpg', 'image/jpeg', UPLOAD_ERR_INI_SIZE, true);

    $this->actingAs(userWith('Admin'))
        ->post('/products', productPayload(['image' => $tooBig]))
        ->assertSessionHasErrors(['image' => __('The image could not be uploaded. It is probably larger than the :size MB limit. Try a smaller photo.', [
            'size' => rtrim(rtrim(number_format(ImageUpload::maxKilobytes() / 1024, 1), '0'), '.'),
        ])]);
});

test('the upload limit never exceeds what PHP accepts', function () {
    expect(ImageUpload::maxKilobytes() * 1024)->toBeLessThanOrEqual(UploadedFile::getMaxFilesize());
});

test('accepted image formats are stored on the public disk', function (string $filename) {
    $this->actingAs(userWith('Admin'))
        ->post('/products', productPayload(['image' => UploadedFile::fake()->image($filename)]))
        ->assertSessionHasNoErrors();

    Storage::disk('public')->assertExists(Product::sole()->image_path);
})->with(['a.jpg', 'a.jpeg', 'a.png', 'a.webp']);

test('a product can be updated', function () {
    $product = Product::factory()->create();

    $this->actingAs(userWith('Admin'))
        ->put("/products/{$product->id}", productPayload(['name' => 'Renamed', 'selling_price' => '99.50']))
        ->assertSessionHasNoErrors();

    expect($product->fresh()->name)->toBe('Renamed')
        ->and((float) $product->fresh()->selling_price)->toBe(99.5);
});

test('products belong to categories and brands', function () {
    $category = Category::factory()->create();
    $brand = Brand::factory()->create();
    $product = Product::factory()->for($category)->for($brand)->create();
    $unbranded = Product::factory()->for($category)->create();

    expect($category->products)->toHaveCount(2)
        ->and($brand->products->sole()->is($product))->toBeTrue()
        ->and($unbranded->brand)->toBeNull();
});

test('products can be searched by name, SKU, barcode and variant code', function () {
    Product::factory()->create(['name' => 'Espresso', 'sku' => 'COF-1']);
    Product::factory()->create(['name' => 'Green Tea', 'sku' => 'TEA-1', 'barcode' => '5000111']);
    $shirt = Product::factory()->create(['name' => 'Polo Shirt', 'sku' => 'POLO']);
    ProductVariant::factory()->for($shirt)->create(['name' => 'XL', 'sku' => 'POLO-XL', 'barcode' => '7770001']);
    $this->actingAs(userWith('Manager'));

    $this->get('/products?search=espr')->assertSee('Espresso')->assertDontSee('Green Tea');
    $this->get('/products?search=TEA-1')->assertSee('Green Tea')->assertDontSee('Espresso');
    $this->get('/products?search=5000111')->assertSee('Green Tea')->assertDontSee('Espresso');
    $this->get('/products?search=7770001')->assertSee('Polo Shirt')->assertDontSee('Espresso');
});

test('products can be filtered by category, brand and status', function () {
    $drinks = Category::factory()->create(['name' => 'Drinks']);
    $acme = Brand::factory()->create(['name' => 'Acme']);
    Product::factory()->for($drinks)->for($acme)->create(['name' => 'Cola']);
    Product::factory()->create(['name' => 'Chips']);
    Product::factory()->inactive()->create(['name' => 'Retired']);
    $this->actingAs(userWith('Admin'));

    $this->get("/products?category={$drinks->id}")->assertSee('Cola')->assertDontSee('Chips');
    $this->get("/products?brand={$acme->id}")->assertSee('Cola')->assertDontSee('Chips');
    $this->get('/products?status=inactive')->assertSee('Retired')->assertDontSee('Cola');
    $this->get('/products?status=active')->assertDontSee('Retired')->assertSee('Chips');
});

test('products are paginated server-side', function () {
    Product::factory()->count(20)->create();

    $this->actingAs(userWith('Admin'))
        ->get('/products')
        ->assertViewHas('products', fn ($products) => $products->count() === 15 && $products->total() === 20);
});

test('deleting a product archives it', function () {
    $product = Product::factory()->create();

    $this->actingAs(userWith('Admin'))
        ->delete("/products/{$product->id}")
        ->assertRedirect(route('products.index'));

    expect(Product::find($product->id))->toBeNull()
        ->and(Product::withTrashed()->find($product->id)->trashed())->toBeTrue();
});

test('products require the matching permission', function () {
    $product = Product::factory()->create();

    $this->actingAs(userWith('Cashier'))->get('/products')->assertForbidden();

    $this->actingAs(userWith('Manager'))->delete("/products/{$product->id}")->assertForbidden();

    expect(Product::count())->toBe(1);
});

test('a product can be toggled inactive', function () {
    $product = Product::factory()->create();

    $this->actingAs(userWith('Admin'))->patch("/products/{$product->id}/toggle");

    expect($product->fresh()->is_active)->toBeFalse();
});
