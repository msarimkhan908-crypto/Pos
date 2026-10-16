<?php

use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Support\Facades\Route;

beforeEach(function () {
    $this->seed(RolesAndPermissionsSeeder::class);
});

function userWithRole(?string $role = null): User
{
    $user = User::factory()->create(['name' => 'Jane Cashier']);

    return $role ? $user->assignRole($role) : $user;
}

test('dashboard renders the application shell', function () {
    $this->actingAs(userWithRole('Cashier'))
        ->get('/dashboard')
        ->assertOk()
        ->assertSee('id="app-sidebar"', false)
        ->assertSee('Jane Cashier')
        ->assertSee('JC')
        ->assertSee(route('profile.edit'), false)
        ->assertSee(route('logout'), false)
        ->assertSee('Notifications')
        ->assertSee('id="global-search"', false);
});

test('profile page uses the application shell', function () {
    $this->actingAs(userWithRole())
        ->get('/profile')
        ->assertOk()
        ->assertSee('id="app-sidebar"', false);
});

test('super admin sees every sidebar section', function () {
    $response = $this->actingAs(userWithRole('Super Admin'))->get('/dashboard');

    foreach (['Sales', 'Catalog', 'Kitchen', 'Reports', 'Management', 'Settings'] as $heading) {
        $response->assertSee($heading);
    }

    $response->assertSeeInOrder([
        'Dashboard', 'POS', 'Orders', 'Customers', 'Products', 'Categories', 'Brands', 'Modifiers',
        'Kitchen Orders', 'Expenses', 'Reports', 'Users',
        'Roles &amp; Permissions', 'General Settings', 'POS Settings', 'Receipt Settings', 'Tax Settings',
    ], false);
});

test('the sidebar has no inventory section', function () {
    $this->actingAs(userWithRole('Super Admin'))
        ->get('/dashboard')
        ->assertDontSee('Inventory')
        ->assertDontSee('Low Stock')
        ->assertDontSee('Stock Adjustment')
        ->assertDontSee('Stock Movements')
        ->assertDontSee('Purchase Orders');
});

test('the purchasing section links to suppliers and purchases', function () {
    $this->actingAs(userWithRole('Manager'))
        ->get('/dashboard')
        ->assertSeeInOrder(['Purchasing', 'Suppliers', 'Purchases'])
        ->assertSee('href="'.route('suppliers.index').'"', false)
        ->assertSee('href="'.route('purchases.index').'"', false)
        ->assertSee('href="'.route('customers.index').'"', false);

    // Cashiers only see POS, not customers or purchasing.
    $this->actingAs(userWithRole('Cashier'))
        ->get('/dashboard')
        ->assertDontSee('href="'.route('customers.index').'"', false)
        ->assertDontSee('Purchasing');
});

test('sidebar hides items the user lacks permission for', function () {
    $this->actingAs(userWithRole('Cashier'))
        ->get('/dashboard')
        ->assertDontSee('Customers')
        ->assertDontSee('Purchase Orders')
        ->assertDontSee('Expenses')
        ->assertDontSee('Roles &amp; Permissions', false)
        ->assertDontSee('Tax Settings');

    $this->actingAs(userWithRole('Kitchen Staff'))
        ->get('/dashboard')
        ->assertSee('Kitchen Orders')
        ->assertDontSee('Customers')
        ->assertDontSee('Users');
});

test('user without a role only sees the dashboard link', function () {
    $this->actingAs(userWithRole())
        ->get('/dashboard')
        ->assertSee('No role has been assigned')
        ->assertDontSee('data-nav-placeholder', false);
});

test('all navigation items are links (no coming-soon placeholders remain)', function () {
    $this->actingAs(userWithRole('Super Admin'))
        ->get('/dashboard')
        ->assertSee('href="'.route('dashboard').'"', false)
        ->assertSee('aria-current="page"', false)
        ->assertDontSee('data-nav-placeholder', false)
        ->assertDontSee('Soon');
});

test('a navigation item becomes a link once its route exists', function () {
    Route::middleware('web')->get('/customers', fn () => 'ok')->name('customers.index');
    Route::getRoutes()->refreshNameLookups();

    $this->actingAs(userWithRole('Manager'))
        ->get('/dashboard')
        ->assertSee('href="'.url('/customers').'"', false);
});
