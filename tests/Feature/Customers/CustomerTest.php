<?php

use App\Enums\RecordStatus;
use App\Models\Customer;
use Database\Seeders\RolesAndPermissionsSeeder;

beforeEach(function () {
    $this->seed(RolesAndPermissionsSeeder::class);
});

/**
 * @param  array<string, mixed>  $overrides
 * @return array<string, mixed>
 */
function customerPayload(array $overrides = []): array
{
    return [
        'name' => 'Sara Ali',
        'phone' => '0300-7654321',
        'email' => 'sara@example.test',
        'address' => 'House 5, Street 9',
        'notes' => 'Prefers extra spicy',
        'status' => 'active',
        ...$overrides,
    ];
}

test('customer pages render', function () {
    $customer = Customer::factory()->create(['name' => 'Bilal Khan']);
    $this->actingAs(userWith('Manager'));

    $this->get('/customers')->assertOk()->assertSee('Bilal Khan');
    $this->get('/customers/create')->assertOk();
    $this->get("/customers/{$customer->id}")->assertOk()->assertSee('No sales found for this customer.');
    $this->get("/customers/{$customer->id}/edit")->assertOk();

    $this->actingAs(userWith('Cashier'))->get('/customers')->assertForbidden();
});

test('a customer can be created', function () {
    $this->actingAs(userWith('Manager'))
        ->post('/customers', customerPayload())
        ->assertSessionHasNoErrors()
        ->assertRedirect();

    $customer = Customer::sole();

    expect($customer->name)->toBe('Sara Ali')
        ->and($customer->phone)->toBe('0300-7654321')
        ->and($customer->notes)->toBe('Prefers extra spicy')
        ->and($customer->status)->toBe(RecordStatus::Active);
});

test('only name and status are required', function () {
    $this->actingAs(userWith('Admin'))
        ->post('/customers', ['name' => 'Walk-in', 'status' => 'active'])
        ->assertSessionHasNoErrors();

    expect(Customer::sole()->phone)->toBeNull();
});

test('customer validation', function (array $data, string $field) {
    $this->actingAs(userWith('Admin'))
        ->post('/customers', customerPayload($data))
        ->assertSessionHasErrors($field);

    expect(Customer::count())->toBe(0);
})->with([
    'name required' => [['name' => ''], 'name'],
    'name too long' => [['name' => str_repeat('a', 151)], 'name'],
    'invalid email' => [['email' => 'sara@'], 'email'],
    'phone with letters' => [['phone' => 'call me'], 'phone'],
    'phone too long' => [['phone' => str_repeat('1', 31)], 'phone'],
    'invalid status' => [['status' => 'vip'], 'status'],
]);

test('a customer can be edited', function () {
    $customer = Customer::factory()->create();

    $this->actingAs(userWith('Manager'))
        ->put("/customers/{$customer->id}", customerPayload(['name' => 'Sara A.', 'status' => 'inactive']))
        ->assertSessionHasNoErrors();

    expect($customer->fresh()->name)->toBe('Sara A.')
        ->and($customer->fresh()->status)->toBe(RecordStatus::Inactive);
});

test('a customer can be deactivated', function () {
    $customer = Customer::factory()->create();

    $this->actingAs(userWith('Admin'))->patch("/customers/{$customer->id}/toggle");

    expect($customer->fresh()->isActive())->toBeFalse();
});

test('a customer can be deleted by an authorized user only', function () {
    $customer = Customer::factory()->create();

    $this->actingAs(userWith('Cashier'))->delete("/customers/{$customer->id}")->assertForbidden();
    $this->actingAs(userWith('Manager'))->delete("/customers/{$customer->id}")->assertForbidden();
    expect(Customer::find($customer->id))->not->toBeNull();

    $this->actingAs(userWith('Admin'))->delete("/customers/{$customer->id}")->assertRedirect(route('customers.index'));
    expect(Customer::find($customer->id))->toBeNull();
});

test('customers require the matching permission', function () {
    $customer = Customer::factory()->create();

    $this->actingAs(userWith('Kitchen Staff'));
    $this->get('/customers')->assertForbidden();
    $this->get("/customers/{$customer->id}")->assertForbidden();
    $this->post('/customers', customerPayload())->assertForbidden();
    $this->put("/customers/{$customer->id}", customerPayload(['name' => 'Hacked']))->assertForbidden();

    auth()->logout();
    $this->get('/customers')->assertRedirect(route('login'));

    expect(Customer::count())->toBe(1)->and($customer->fresh()->name)->not->toBe('Hacked');
});

test('customers can be searched and filtered by status', function () {
    Customer::factory()->create(['name' => 'Ayesha Noor', 'phone' => '0321-1112223', 'email' => 'ayesha@x.test']);
    Customer::factory()->create(['name' => 'Usman Tariq', 'phone' => '0333-9998887', 'email' => 'usman@x.test']);
    Customer::factory()->inactive()->create(['name' => 'Former Client']);
    $this->actingAs(userWith('Manager'));

    $this->get('/customers?search=ayesha')->assertSee('Ayesha Noor')->assertDontSee('Usman Tariq');
    $this->get('/customers?search=9998887')->assertSee('Usman Tariq')->assertDontSee('Ayesha Noor');
    $this->get('/customers?search=ayesha@x')->assertSee('Ayesha Noor');
    $this->get('/customers?status=inactive')->assertSee('Former Client')->assertDontSee('Usman Tariq');
    $this->get('/customers?status=active')->assertDontSee('Former Client');
});

test('customers are paginated', function () {
    Customer::factory()->count(17)->create();

    $this->actingAs(userWith('Admin'))
        ->get('/customers')
        ->assertViewHas('customers', fn ($customers) => $customers->count() === 15 && $customers->total() === 17);
});

test('the dashboard counts real customers', function () {
    Customer::factory()->count(3)->create();

    $this->actingAs(userWith('Admin'))
        ->get('/dashboard')
        ->assertViewHas('stats', fn (array $stats) => collect($stats)->firstWhere('label', 'Total Customers')['value'] === '3');
});
