<?php

use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Support\Facades\Route;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

beforeEach(function () {
    $this->seed(RolesAndPermissionsSeeder::class);
});

test('seeder creates all roles and permissions', function () {
    expect(Role::pluck('name')->sort()->values()->all())
        ->toBe(['Admin', 'Cashier', 'Kitchen Staff', 'Manager', 'Super Admin']);

    expect(Permission::count())->toBe(count(RolesAndPermissionsSeeder::allPermissions()))
        ->and(Permission::pluck('name')->all())
        ->toEqualCanonicalizing(RolesAndPermissionsSeeder::allPermissions());
});

test('seeder is idempotent', function () {
    $this->seed(RolesAndPermissionsSeeder::class);

    expect(Role::count())->toBe(5)
        ->and(Permission::count())->toBe(count(RolesAndPermissionsSeeder::allPermissions()));
});

test('super admin has every permission', function () {
    $user = User::factory()->create()->assignRole('Super Admin');

    foreach (RolesAndPermissionsSeeder::allPermissions() as $permission) {
        expect($user->can($permission))->toBeTrue();
    }
});

test('removed inventory permissions are deleted by the seeder', function () {
    Permission::findOrCreate('inventory.adjust', 'web');

    $this->seed(RolesAndPermissionsSeeder::class);

    expect(Permission::where('name', 'like', 'inventory.%')->exists())->toBeFalse();
});

test('super admin role holds exactly the full permission set', function () {
    expect(Role::findByName('Super Admin')->permissions->pluck('name')->all())
        ->toEqualCanonicalizing(RolesAndPermissionsSeeder::allPermissions());
});

test('a user can be assigned and removed from a role', function () {
    $user = User::factory()->create();

    expect($user->hasRole('Manager'))->toBeFalse()
        ->and($user->can('products.create'))->toBeFalse();

    $user->assignRole('Manager');

    expect($user->hasRole('Manager'))->toBeTrue()
        ->and($user->getRoleNames()->all())->toBe(['Manager'])
        ->and($user->can('products.create'))->toBeTrue()
        ->and($user->can('users.create'))->toBeFalse();

    $user->removeRole('Manager');

    expect($user->fresh()->hasRole('Manager'))->toBeFalse()
        ->and($user->fresh()->can('products.create'))->toBeFalse();
});

test('a user can be granted a direct permission', function () {
    $user = User::factory()->create()->assignRole('Cashier');

    expect($user->can('reports.view'))->toBeFalse();

    $user->givePermissionTo('reports.view');

    expect($user->can('reports.view'))->toBeTrue()
        ->and($user->hasDirectPermission('reports.view'))->toBeTrue()
        ->and($user->hasPermissionTo('sales.create'))->toBeTrue();
});

test('checking an unknown permission is denied rather than erroring', function () {
    $user = User::factory()->create()->assignRole('Super Admin');

    expect($user->can('does.not.exist'))->toBeFalse();
});

test('roles receive their configured permissions', function (string $role, array $allowed, array $denied) {
    $user = User::factory()->create()->assignRole($role);

    foreach ($allowed as $permission) {
        expect($user->can($permission))->toBeTrue("{$role} should have {$permission}");
    }

    foreach ($denied as $permission) {
        expect($user->can($permission))->toBeFalse("{$role} should not have {$permission}");
    }
})->with([
    'admin' => ['Admin', ['users.delete', 'settings.manage', 'roles.view'], ['roles.create', 'roles.edit', 'roles.delete']],
    'manager' => ['Manager', ['products.create', 'categories.edit', 'reports.view'], ['users.create', 'sales.delete', 'settings.manage']],
    'cashier' => ['Cashier', ['sales.create'], ['sales.view', 'customers.view', 'products.view', 'sales.delete', 'reports.view']],
    'kitchen staff' => ['Kitchen Staff', ['kitchen.view', 'kitchen.manage'], ['sales.create', 'customers.view', 'users.view']],
]);

test('permission middleware guards routes', function () {
    Route::middleware(['web', 'auth', 'permission:reports.view'])
        ->get('/_test/reports', fn () => 'ok');

    $this->actingAs(User::factory()->create()->assignRole('Manager'))
        ->get('/_test/reports')
        ->assertOk();

    $this->actingAs(User::factory()->create()->assignRole('Cashier'))
        ->get('/_test/reports')
        ->assertForbidden();
});
