<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

class RolesAndPermissionsSeeder extends Seeder
{
    public const GUARD = 'web';

    /**
     * All POS permissions, grouped by module.
     *
     * @var array<string, list<string>>
     */
    public const PERMISSIONS = [
        'users' => ['view', 'create', 'edit', 'delete'],
        'roles' => ['view', 'create', 'edit', 'delete'],
        'categories' => ['view', 'create', 'edit', 'delete'],
        'brands' => ['view', 'create', 'edit', 'delete'],
        'products' => ['view', 'create', 'edit', 'delete'],
        'sales' => ['view', 'create', 'edit', 'delete'],
        'customers' => ['view', 'create', 'edit', 'delete'],
        'suppliers' => ['view', 'create', 'edit', 'delete'],
        'purchases' => ['view', 'create', 'edit', 'delete'],
        'reports' => ['view'],
        'settings' => ['view', 'manage'],
        'kitchen' => ['view', 'manage'],
        'tables' => ['view', 'create', 'edit', 'delete', 'status'],
        'returns' => ['view', 'create', 'edit', 'delete'],
        'expenses' => ['view', 'create', 'edit', 'delete'],
    ];

    /**
     * Permissions per role. Super Admin always receives every permission.
     *
     * @var array<string, list<string>>
     */
    public const ROLE_PERMISSIONS = [
        'Admin' => [
            'users.view', 'users.create', 'users.edit', 'users.delete',
            'roles.view',
            'categories.view', 'categories.create', 'categories.edit', 'categories.delete',
            'brands.view', 'brands.create', 'brands.edit', 'brands.delete',
            'products.view', 'products.create', 'products.edit', 'products.delete',
            'sales.view', 'sales.create', 'sales.edit', 'sales.delete',
            'customers.view', 'customers.create', 'customers.edit', 'customers.delete',
            'suppliers.view', 'suppliers.create', 'suppliers.edit', 'suppliers.delete',
            'purchases.view', 'purchases.create', 'purchases.edit', 'purchases.delete',
            'reports.view',
            'settings.view', 'settings.manage',
            'kitchen.view', 'kitchen.manage',
            'tables.view', 'tables.create', 'tables.edit', 'tables.delete', 'tables.status',
            'returns.view', 'returns.create', 'returns.edit', 'returns.delete',
            'expenses.view', 'expenses.create', 'expenses.edit', 'expenses.delete',
        ],
        'Manager' => [
            'users.view',
            'categories.view', 'categories.create', 'categories.edit',
            'brands.view', 'brands.create', 'brands.edit',
            'products.view', 'products.create', 'products.edit',
            'sales.view', 'sales.create', 'sales.edit',
            'customers.view', 'customers.create', 'customers.edit',
            'suppliers.view', 'suppliers.create', 'suppliers.edit',
            'purchases.view', 'purchases.create', 'purchases.edit',
            'reports.view',
            'settings.view',
            'kitchen.view', 'kitchen.manage',
            'tables.view', 'tables.create', 'tables.edit', 'tables.status',
            'returns.view', 'returns.create', 'returns.edit',
            'expenses.view', 'expenses.create', 'expenses.edit',
        ],
        'Cashier' => [
            'sales.create',
        ],
        'Kitchen Staff' => [
            'products.view',
            'kitchen.view', 'kitchen.manage',
        ],
    ];

    public const SUPER_ADMIN = 'Super Admin';

    /**
     * Seed roles and permissions. Safe to run repeatedly.
     */
    public function run(): void
    {
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        foreach (self::allPermissions() as $name) {
            Permission::findOrCreate($name, self::GUARD);
        }

        // This list is the source of truth: remove permissions that are no longer defined.
        Permission::where('guard_name', self::GUARD)->whereNotIn('name', self::allPermissions())->delete();

        Role::findOrCreate(self::SUPER_ADMIN, self::GUARD)
            ->syncPermissions(Permission::where('guard_name', self::GUARD)->get());

        foreach (self::ROLE_PERMISSIONS as $role => $permissions) {
            Role::findOrCreate($role, self::GUARD)->syncPermissions($permissions);
        }

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    /**
     * @return list<string>
     */
    public static function allPermissions(): array
    {
        $names = [];

        foreach (self::PERMISSIONS as $module => $actions) {
            foreach ($actions as $action) {
                $names[] = "{$module}.{$action}";
            }
        }

        return $names;
    }
}
