<?php

use App\Models\Brand;
use App\Models\Product;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

beforeEach(function () {
    $this->seed(RolesAndPermissionsSeeder::class);
    Storage::fake('public');
});

test('brand pages render', function () {
    $brand = Brand::factory()->create(['name' => 'Acme']);
    Product::factory()->for($brand)->create(['name' => 'Acme Juice']);
    $this->actingAs(userWith('Admin'));

    $this->get('/brands')->assertOk()->assertSee('Acme');
    $this->get('/brands/create')->assertOk();
    $this->get("/brands/{$brand->id}")->assertOk()->assertSee('Acme Juice');
    $this->get("/brands/{$brand->id}/edit")->assertOk();
});

test('a brand can be created with a logo', function () {
    $this->actingAs(userWith('Manager'))
        ->post('/brands', ['name' => 'Nestle', 'is_active' => '1', 'logo' => UploadedFile::fake()->image('logo.jpg')])
        ->assertSessionHasNoErrors();

    $brand = Brand::firstWhere('name', 'Nestle');

    expect($brand->slug)->toBe('nestle')->and($brand->logo_path)->toStartWith('brands/');
    Storage::disk('public')->assertExists($brand->logo_path);
});

test('brand names must be unique', function () {
    Brand::factory()->create(['name' => 'Nestle']);

    $this->actingAs(userWith('Admin'))
        ->post('/brands', ['name' => 'Nestle', 'is_active' => '1'])
        ->assertSessionHasErrors('name');
});

test('brands require the matching permission', function () {
    $this->actingAs(userWith('Cashier'))->get('/brands')->assertForbidden();

    $this->actingAs(userWith('Kitchen Staff'))->get('/brands')->assertForbidden();
});

test('a brand in use cannot be deleted, an unused one can', function () {
    $used = Brand::factory()->create();
    Product::factory()->for($used)->create();
    $unused = Brand::factory()->create();
    $this->actingAs(userWith('Admin'));

    $this->delete("/brands/{$used->id}")->assertSessionHas('error');
    $this->delete("/brands/{$unused->id}")->assertRedirect(route('brands.index'));

    expect(Brand::find($used->id))->not->toBeNull()
        ->and(Brand::find($unused->id))->toBeNull();
});
