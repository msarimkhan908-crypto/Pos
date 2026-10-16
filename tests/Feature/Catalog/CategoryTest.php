<?php

use App\Models\Category;
use App\Models\Product;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

beforeEach(function () {
    $this->seed(RolesAndPermissionsSeeder::class);
    Storage::fake('public');
});

test('category pages render', function () {
    $category = Category::factory()->create(['name' => 'Beverages']);
    Product::factory()->for($category)->create(['name' => 'Cola']);
    $this->actingAs(userWith('Admin'));

    $this->get('/categories')->assertOk()->assertSee('Beverages');
    $this->get('/categories/create')->assertOk();
    $this->get("/categories/{$category->id}")->assertOk()->assertSee('Cola');
    $this->get("/categories/{$category->id}/edit")->assertOk();
});

test('a category can be created with a generated slug and an image', function () {
    $this->actingAs(userWith('Admin'))
        ->post('/categories', [
            'name' => 'Hot Drinks',
            'description' => 'Tea and coffee',
            'is_active' => '1',
            'sort_order' => 3,
            'image' => UploadedFile::fake()->image('drinks.png', 300, 300),
        ])
        ->assertRedirect()
        ->assertSessionHasNoErrors();

    $category = Category::firstWhere('name', 'Hot Drinks');

    expect($category->slug)->toBe('hot-drinks')
        ->and($category->is_active)->toBeTrue()
        ->and($category->sort_order)->toBe(3)
        ->and($category->image_path)->toStartWith('categories/');

    Storage::disk('public')->assertExists($category->image_path);
});

test('duplicate slugs get a suffix', function () {
    Category::factory()->create(['name' => 'Snacks', 'slug' => 'snacks']);

    $this->actingAs(userWith('Admin'))->post('/categories', ['name' => 'Snacks!', 'is_active' => '1']);

    expect(Category::firstWhere('name', 'Snacks!')->slug)->toBe('snacks-2');
});

test('category validation', function (array $data, string $field) {
    Category::factory()->create(['name' => 'Existing']);

    $this->actingAs(userWith('Admin'))
        ->post('/categories', [...['name' => 'Valid', 'is_active' => '1'], ...$data])
        ->assertSessionHasErrors($field);
})->with([
    'name required' => [['name' => ''], 'name'],
    'name unique' => [['name' => 'Existing'], 'name'],
    'name too long' => [['name' => str_repeat('a', 101)], 'name'],
    'negative sort order' => [['sort_order' => -1], 'sort_order'],
    'image must be an image' => [['image' => UploadedFile::fake()->create('menu.pdf', 10, 'application/pdf')], 'image'],
]);

test('categories require the matching permission', function () {
    $category = Category::factory()->create();

    // Cashier and Kitchen Staff: no access.
    $this->actingAs(userWith('Cashier'))->get('/categories')->assertForbidden();
    $this->actingAs(userWith('Kitchen Staff'))->get('/categories')->assertForbidden();

    // Manager: can edit but not delete.
    $this->actingAs(userWith('Manager'));
    $this->get("/categories/{$category->id}/edit")->assertOk();
    $this->delete("/categories/{$category->id}")->assertForbidden();

    expect(Category::count())->toBe(1)->and(Category::where('name', 'Nope')->exists())->toBeFalse();
});

test('guests are redirected to login', function () {
    $this->get('/categories')->assertRedirect(route('login'));
});

test('a category in use cannot be deleted', function () {
    $category = Category::factory()->create();
    Product::factory()->for($category)->create();

    $this->actingAs(userWith('Admin'))
        ->delete("/categories/{$category->id}")
        ->assertSessionHas('error');

    expect($category->fresh())->not->toBeNull();
});

test('an unused category can be deleted and its image removed', function () {
    $path = UploadedFile::fake()->image('x.png')->store('categories', 'public');
    $category = Category::factory()->create(['image_path' => $path]);

    $this->actingAs(userWith('Admin'))
        ->delete("/categories/{$category->id}")
        ->assertRedirect(route('categories.index'));

    expect(Category::find($category->id))->toBeNull();
    Storage::disk('public')->assertMissing($path);
});

test('a category can be deactivated and reactivated', function () {
    $category = Category::factory()->create();
    $this->actingAs(userWith('Admin'));

    $this->patch("/categories/{$category->id}/toggle");
    expect($category->fresh()->is_active)->toBeFalse();

    $this->patch("/categories/{$category->id}/toggle");
    expect($category->fresh()->is_active)->toBeTrue();
});

test('updating can replace or remove the image', function () {
    $old = UploadedFile::fake()->image('old.png')->store('categories', 'public');
    $category = Category::factory()->create(['name' => 'Food', 'image_path' => $old]);
    $this->actingAs(userWith('Admin'));

    $this->put("/categories/{$category->id}", ['name' => 'Food', 'is_active' => '1', 'image' => UploadedFile::fake()->image('new.webp')]);
    $new = $category->fresh()->image_path;

    expect($new)->not->toBe($old);
    Storage::disk('public')->assertMissing($old);
    Storage::disk('public')->assertExists($new);

    $this->put("/categories/{$category->id}", ['name' => 'Food', 'is_active' => '1', 'remove_image' => '1']);

    expect($category->fresh()->image_path)->toBeNull();
    Storage::disk('public')->assertMissing($new);
});

test('categories can be searched and filtered by status', function () {
    Category::factory()->create(['name' => 'Desserts']);
    Category::factory()->inactive()->create(['name' => 'Seasonal']);
    $this->actingAs(userWith('Admin'));

    $this->get('/categories?search=dess')->assertSee('Desserts')->assertDontSee('Seasonal');
    $this->get('/categories?status=inactive')->assertSee('Seasonal')->assertDontSee('Desserts');
});
