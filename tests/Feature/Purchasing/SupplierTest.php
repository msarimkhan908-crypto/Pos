<?php

use App\Enums\RecordStatus;
use App\Models\Purchase;
use App\Models\Supplier;
use Database\Seeders\RolesAndPermissionsSeeder;

beforeEach(function () {
    $this->seed(RolesAndPermissionsSeeder::class);
});

/**
 * @param  array<string, mixed>  $overrides
 * @return array<string, mixed>
 */
function supplierPayload(array $overrides = []): array
{
    return [
        'name' => 'Ahmed Traders',
        'company_name' => 'Ahmed Trading Co.',
        'phone' => '+92 300 1234567',
        'email' => 'orders@ahmed.test',
        'address' => '12 Market Road, Lahore',
        'tax_number' => 'NTN-12345',
        'registration_number' => 'REG-678',
        'notes' => 'Delivers on Mondays',
        'status' => 'active',
        ...$overrides,
    ];
}

test('supplier pages render', function () {
    $supplier = Supplier::factory()->create(['name' => 'Fresh Foods']);
    $this->actingAs(userWith('Admin'));

    $this->get('/suppliers')->assertOk()->assertSee('Fresh Foods');
    $this->get('/suppliers/create')->assertOk();
    $this->get("/suppliers/{$supplier->id}")->assertOk()->assertSee('No purchases found for this supplier.');
    $this->get("/suppliers/{$supplier->id}/edit")->assertOk();
});

test('a supplier can be created', function () {
    $this->actingAs(userWith('Manager'))
        ->post('/suppliers', supplierPayload())
        ->assertSessionHasNoErrors()
        ->assertRedirect();

    $supplier = Supplier::sole();

    expect($supplier->name)->toBe('Ahmed Traders')
        ->and($supplier->company_name)->toBe('Ahmed Trading Co.')
        ->and($supplier->tax_number)->toBe('NTN-12345')
        ->and($supplier->status)->toBe(RecordStatus::Active);
});

test('only name and status are required', function () {
    $this->actingAs(userWith('Admin'))
        ->post('/suppliers', ['name' => 'Walk-in Vendor', 'status' => 'inactive'])
        ->assertSessionHasNoErrors();

    expect(Supplier::sole()->status)->toBe(RecordStatus::Inactive);
});

test('supplier validation', function (array $data, string $field) {
    $this->actingAs(userWith('Admin'))
        ->post('/suppliers', supplierPayload($data))
        ->assertSessionHasErrors($field);

    expect(Supplier::count())->toBe(0);
})->with([
    'name required' => [['name' => ''], 'name'],
    'name too long' => [['name' => str_repeat('a', 151)], 'name'],
    'invalid email' => [['email' => 'not-an-email'], 'email'],
    'phone with letters' => [['phone' => '0300-CALL-ME'], 'phone'],
    'phone too short' => [['phone' => '123'], 'phone'],
    'invalid status' => [['status' => 'deleted'], 'status'],
    'status required' => [['status' => ''], 'status'],
]);

test('a supplier can be edited', function () {
    $supplier = Supplier::factory()->create();

    $this->actingAs(userWith('Manager'))
        ->put("/suppliers/{$supplier->id}", supplierPayload(['name' => 'Renamed Traders', 'email' => '']))
        ->assertSessionHasNoErrors();

    expect($supplier->fresh()->name)->toBe('Renamed Traders')
        ->and($supplier->fresh()->email)->toBeNull();
});

test('a supplier can be deactivated and reactivated', function () {
    $supplier = Supplier::factory()->create();
    $this->actingAs(userWith('Admin'));

    $this->patch("/suppliers/{$supplier->id}/toggle");
    expect($supplier->fresh()->status)->toBe(RecordStatus::Inactive);

    $this->patch("/suppliers/{$supplier->id}/toggle");
    expect($supplier->fresh()->status)->toBe(RecordStatus::Active);
});

test('a supplier with purchases cannot be deleted; one without can', function () {
    $used = Supplier::factory()->create();
    Purchase::factory()->for($used)->create();
    $unused = Supplier::factory()->create();
    $this->actingAs(userWith('Admin'));

    $this->delete("/suppliers/{$used->id}")->assertSessionHas('error');
    $this->delete("/suppliers/{$unused->id}")->assertRedirect(route('suppliers.index'));

    expect(Supplier::find($used->id))->not->toBeNull()
        ->and(Supplier::find($unused->id))->toBeNull();
});

test('suppliers require the matching permission', function () {
    $supplier = Supplier::factory()->create();

    // Cashier: no supplier access at all.
    $this->actingAs(userWith('Cashier'));
    $this->get('/suppliers')->assertForbidden();
    $this->get("/suppliers/{$supplier->id}")->assertForbidden();
    $this->post('/suppliers', supplierPayload())->assertForbidden();

    // Manager: can manage but not delete.
    $this->actingAs(userWith('Manager'));
    $this->get("/suppliers/{$supplier->id}/edit")->assertOk();
    $this->delete("/suppliers/{$supplier->id}")->assertForbidden();

    auth()->logout();
    $this->get('/suppliers')->assertRedirect(route('login'));

    expect(Supplier::count())->toBe(1);
});

test('suppliers can be searched and filtered by status', function () {
    Supplier::factory()->create(['name' => 'Karachi Spices', 'company_name' => 'KS Ltd', 'phone' => '021-5550001']);
    Supplier::factory()->create(['name' => 'Lahore Dairy', 'company_name' => 'LD Pvt', 'email' => 'milk@lahore.test']);
    Supplier::factory()->inactive()->create(['name' => 'Old Vendor']);
    $this->actingAs(userWith('Admin'));

    $this->get('/suppliers?search=spices')->assertSee('Karachi Spices')->assertDontSee('Lahore Dairy');
    $this->get('/suppliers?search=LD Pvt')->assertSee('Lahore Dairy')->assertDontSee('Karachi Spices');
    $this->get('/suppliers?search=5550001')->assertSee('Karachi Spices');
    $this->get('/suppliers?search=milk@lahore')->assertSee('Lahore Dairy');
    $this->get('/suppliers?status=inactive')->assertSee('Old Vendor')->assertDontSee('Karachi Spices');
    $this->get('/suppliers?status=bogus')->assertOk()->assertSee('Karachi Spices');
});

test('suppliers are paginated', function () {
    Supplier::factory()->count(18)->create();

    $this->actingAs(userWith('Admin'))
        ->get('/suppliers')
        ->assertViewHas('suppliers', fn ($suppliers) => $suppliers->count() === 15 && $suppliers->total() === 18);
});
