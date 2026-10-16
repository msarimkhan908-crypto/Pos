<?php

use App\Enums\TableStatus;
use App\Models\Category;
use App\Models\KitchenStation;
use App\Models\Product;
use App\Models\RestaurantTable;
use App\Models\Sale;
use Database\Seeders\RestaurantSeeder;
use Database\Seeders\RolesAndPermissionsSeeder;

beforeEach(function () {
    $this->seed(RolesAndPermissionsSeeder::class);
});

// ------------------------------------------------------------------ tables

test('admins manage tables', function () {
    $this->actingAs(userWith('Admin'));

    $this->get('/restaurant/tables')->assertOk()->assertSee('No tables yet');
    $this->get('/restaurant/tables/create')->assertOk();

    $this->post('/restaurant/tables', ['name' => 'Table 12', 'section' => 'Patio', 'capacity' => 6, 'status' => 'available', 'is_active' => '1'])
        ->assertRedirect('/restaurant/tables');

    $table = RestaurantTable::sole();
    expect([$table->name, $table->section, $table->capacity, $table->status, $table->is_active])
        ->toBe(['Table 12', 'Patio', 6, TableStatus::Available, true]);

    $this->get('/restaurant/tables')->assertOk()->assertSee('Table 12')->assertSee('Patio')->assertSee('6 seats');
    $this->get("/restaurant/tables/{$table->id}/edit")->assertOk()->assertSee('Table 12');

    $this->put("/restaurant/tables/{$table->id}", ['name' => 'Table 12A', 'status' => 'reserved'])->assertRedirect();
    expect($table->fresh())->name->toBe('Table 12A')->status->toBe(TableStatus::Reserved)->is_active->toBeFalse();

    $this->delete("/restaurant/tables/{$table->id}")->assertRedirect('/restaurant/tables');
    expect(RestaurantTable::count())->toBe(0);
});

test('table names are required and unique', function () {
    RestaurantTable::factory()->create(['name' => 'Table 1']);
    $this->actingAs(userWith('Admin'));

    $this->post('/restaurant/tables', ['name' => '', 'status' => 'available'])->assertSessionHasErrors('name');
    $this->post('/restaurant/tables', ['name' => 'Table 1', 'status' => 'available'])->assertSessionHasErrors('name');
    $this->post('/restaurant/tables', ['name' => 'Table 2', 'status' => 'broken'])->assertSessionHasErrors('status');
    $this->post('/restaurant/tables', ['name' => 'Table 2', 'status' => 'available', 'capacity' => 0])->assertSessionHasErrors('capacity');
});

test('tables with orders cannot be deleted', function () {
    $table = RestaurantTable::factory()->create();
    Sale::forceCreate(['status' => 'completed', 'order_type' => 'dine_in', 'table_id' => $table->id]);

    $this->actingAs(userWith('Admin'))->delete("/restaurant/tables/{$table->id}")->assertSessionHas('error');

    expect(RestaurantTable::count())->toBe(1);
});

test('managers see tables and change their status; cashiers have no table access', function () {
    $table = RestaurantTable::factory()->create(['name' => 'Table 4']);

    $this->actingAs(userWith('Cashier'))->get('/restaurant/tables')->assertForbidden();

    $this->actingAs(userWith('Manager'));
    $this->get('/restaurant/tables')->assertOk()->assertSee('Table 4');
    $this->get('/restaurant/tables/create')->assertOk();
    $this->delete("/restaurant/tables/{$table->id}")->assertForbidden();

    $this->patch("/restaurant/tables/{$table->id}/status", ['status' => 'reserved'])->assertRedirect()->assertSessionHas('success');
    expect($table->fresh()->status)->toBe(TableStatus::Reserved);

    $this->patchJson("/restaurant/tables/{$table->id}/status", ['status' => 'available'])
        ->assertOk()->assertJson(['status' => 'available']);
    $this->patchJson("/restaurant/tables/{$table->id}/status", ['status' => 'dirty'])->assertUnprocessable();
});

test('kitchen staff cannot open the tables page', function () {
    $this->actingAs(userWith('Kitchen Staff'))->get('/restaurant/tables')->assertForbidden();
});

test('the tables page counts tables by status', function () {
    RestaurantTable::factory()->count(2)->create();
    RestaurantTable::factory()->create(['status' => 'occupied']);
    RestaurantTable::factory()->create(['status' => 'reserved']);

    $this->actingAs(userWith('Manager'))
        ->get('/restaurant/tables')
        ->assertOk()
        ->assertSee('data-count="available">2<', false)
        ->assertSee('data-count="occupied">1<', false)
        ->assertSee('data-count="reserved">1<', false);
});

// ------------------------------------------------------------------ kitchen stations

test('admins manage kitchen stations; managers can only view them', function () {
    $this->actingAs(userWith('Admin'));

    $this->post('/kitchen/stations', ['name' => 'Grill', 'description' => 'Burgers and kebabs', 'is_active' => '1'])->assertRedirect('/kitchen/stations');
    $station = KitchenStation::sole();
    expect([$station->name, $station->description, $station->is_active])->toBe(['Grill', 'Burgers and kebabs', true]);

    $this->get('/kitchen/stations')->assertOk()->assertSee('Grill')->assertSee('Burgers and kebabs');
    $this->put("/kitchen/stations/{$station->id}", ['name' => 'Charcoal Grill'])->assertRedirect();
    expect($station->fresh()->name)->toBe('Charcoal Grill');
    $this->post('/kitchen/stations', ['name' => 'Charcoal Grill'])->assertSessionHasErrors('name');

    $this->actingAs(userWith('Manager'));
    $this->get('/kitchen/stations')->assertOk();
    $this->get('/kitchen/stations/create')->assertForbidden();
    $this->post('/kitchen/stations', ['name' => 'Pizza'])->assertForbidden();

    $this->actingAs(userWith('Kitchen Staff'))->get('/kitchen/stations')->assertForbidden();
});

test('deleting a station unassigns its categories and products', function () {
    $station = KitchenStation::factory()->create();
    $category = Category::factory()->create(['kitchen_station_id' => $station->id]);
    $product = Product::factory()->create(['kitchen_station_id' => $station->id]);

    $this->actingAs(userWith('Admin'))->delete("/kitchen/stations/{$station->id}")->assertRedirect();

    expect($category->fresh()->kitchen_station_id)->toBeNull()
        ->and($product->fresh()->kitchen_station_id)->toBeNull();
});

test('categories and products can be assigned a kitchen station', function () {
    $grill = KitchenStation::factory()->create(['name' => 'Grill']);
    $this->actingAs(userWith('Admin'));

    $this->get('/categories/create')->assertOk()->assertSee('Kitchen station')->assertSee('Grill');
    $this->post('/categories', ['name' => 'Burgers', 'is_active' => '1', 'kitchen_station_id' => $grill->id])->assertRedirect();
    expect(Category::sole()->kitchenStation->name)->toBe('Grill');

    $this->get('/products/create')->assertOk()->assertSee('Kitchen station');
    $this->post('/products', [
        'product_type' => 'simple', 'name' => 'Zinger', 'selling_price' => '450', 'is_active' => '1',
        'kitchen_station_id' => $grill->id,
    ])->assertRedirect();
    expect(Product::sole()->kitchen_station_id)->toBe($grill->id);

    $this->post('/categories', ['name' => 'Bad', 'kitchen_station_id' => 999])->assertSessionHasErrors('kitchen_station_id');
});

// ------------------------------------------------------------------ navigation & setup

test('the sidebar links to tables, the kitchen and kitchen stations', function () {
    $this->actingAs(userWith('Admin'))
        ->get('/dashboard')
        ->assertSeeInOrder(['Restaurant', 'Tables', 'Kitchen Orders'])
        ->assertSee('href="'.route('restaurant.tables.index').'"', false)
        ->assertSee('href="'.route('kitchen.index').'"', false)
        ->assertSee('href="'.route('kitchen.stations.index').'"', false);

    $this->actingAs(userWith('Kitchen Staff'))
        ->get('/dashboard')
        ->assertSee('href="'.route('kitchen.index').'"', false)
        ->assertDontSee('href="'.route('restaurant.tables.index').'"', false);
});

test('the restaurant seeder creates the stations and tables once', function () {
    $this->seed(RestaurantSeeder::class);
    $this->seed(RestaurantSeeder::class);

    expect(KitchenStation::orderBy('sort_order')->pluck('name')->all())->toBe(['Main Kitchen', 'Grill', 'Pizza', 'Drinks', 'Dessert'])
        ->and(RestaurantTable::count())->toBe(12)
        ->and(RestaurantTable::where('name', 'Table 12')->exists())->toBeTrue();
});
