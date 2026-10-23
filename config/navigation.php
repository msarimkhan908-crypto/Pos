<?php

/*
|--------------------------------------------------------------------------
| Sidebar Navigation
|--------------------------------------------------------------------------
|
| Each item may define:
|   label       Display text (translated when rendered).
|   route       Route name. If the route is not registered yet, the item is
|               rendered as a disabled "Soon" placeholder instead of a link.
|   active      Route name pattern(s) that mark the item as active.
|   icon        Name of an icon in the <x-icon> component.
|   permission  Spatie permission required to see the item (optional).
|
| Sections with no visible items are hidden automatically.
|
*/

return [

    'sidebar' => [
        [
            'heading' => null,
            'items' => [
                ['label' => 'Dashboard', 'route' => 'dashboard', 'active' => 'dashboard', 'icon' => 'dashboard'],
            ],
        ],
        [
            'heading' => 'Sales',
            'items' => [
                ['label' => 'POS', 'route' => 'pos.index', 'active' => 'pos.*', 'icon' => 'pos', 'permission' => 'sales.create'],
                ['label' => 'Orders', 'route' => 'orders.index', 'active' => 'orders.*', 'icon' => 'orders', 'permission' => 'sales.view'],
                ['label' => 'Customers', 'route' => 'customers.index', 'active' => 'customers.*', 'icon' => 'customers', 'permission' => 'customers.view'],
                ['label' => 'Returns', 'route' => 'returns.index', 'active' => 'returns.*', 'icon' => 'history', 'permission' => 'returns.view'],
            ],
        ],
        [
            'heading' => 'Catalog',
            'items' => [
                ['label' => 'Products', 'route' => 'products.index', 'active' => 'products.*', 'icon' => 'products', 'permission' => 'products.view'],
                ['label' => 'Categories', 'route' => 'categories.index', 'active' => 'categories.*', 'icon' => 'categories', 'permission' => 'categories.view'],
                ['label' => 'Brands', 'route' => 'brands.index', 'active' => 'brands.*', 'icon' => 'brands', 'permission' => 'brands.view'],
                ['label' => 'Modifiers', 'route' => 'modifier-groups.index', 'active' => 'modifier-groups.*', 'icon' => 'modifiers', 'permission' => 'products.view', 'restaurant_only' => true],
            ],
        ],
        [
            'heading' => 'Purchasing',
            'items' => [
                ['label' => 'Suppliers', 'route' => 'suppliers.index', 'active' => 'suppliers.*', 'icon' => 'suppliers', 'permission' => 'suppliers.view'],
                ['label' => 'Purchases', 'route' => 'purchases.index', 'active' => 'purchases.*', 'icon' => 'purchase-orders', 'permission' => 'purchases.view'],
            ],
        ],
        [
            'heading' => 'Restaurant',
            'restaurant_only' => true,
            'items' => [
                ['label' => 'Tables', 'route' => 'restaurant.tables.index', 'active' => 'restaurant.tables.*', 'icon' => 'table', 'permission' => 'tables.view', 'restaurant_only' => true],
                ['label' => 'Kitchen Orders', 'route' => 'kitchen.index', 'active' => ['kitchen.index', 'kitchen.orders*'], 'icon' => 'kitchen', 'permission' => 'kitchen.view', 'restaurant_only' => true],
            ],
        ],
        [
            'heading' => 'Finance',
            'items' => [
                ['label' => 'Expenses', 'route' => 'expenses.index', 'active' => ['expenses.*', 'expense-categories.*'], 'icon' => 'banknote', 'permission' => 'expenses.view'],
                ['label' => 'Reports', 'route' => 'reports.index', 'active' => 'reports.*', 'icon' => 'sales-reports', 'permission' => 'reports.view'],
            ],
        ],
        [
            'heading' => 'Management',
            'items' => [
                ['label' => 'Users', 'route' => 'users.index', 'active' => 'users.*', 'icon' => 'users', 'permission' => 'users.view'],
                ['label' => 'Roles & Permissions', 'route' => 'roles.index', 'active' => 'roles.*', 'icon' => 'roles', 'permission' => 'roles.view'],
            ],
        ],
        [
            'heading' => 'Settings',
            'items' => [
                ['label' => 'General Settings', 'route' => 'settings.general', 'active' => ['settings.general', 'settings.business', 'settings.appearance', 'settings.dashboard'], 'icon' => 'settings', 'permission' => 'settings.view'],
                ['label' => 'POS Settings', 'route' => 'settings.pos', 'active' => 'settings.pos*', 'icon' => 'pos-settings', 'permission' => 'settings.view'],
                ['label' => 'Receipt Settings', 'route' => 'settings.receipt', 'active' => ['settings.receipt', 'settings.kot'], 'icon' => 'receipt', 'permission' => 'settings.view'],
                ['label' => 'Tax Settings', 'route' => 'settings.tax', 'active' => 'settings.tax*', 'icon' => 'tax', 'permission' => 'settings.view'],
                ['label' => 'Kitchen Stations', 'route' => 'kitchen.stations.index', 'active' => 'kitchen.stations.*', 'icon' => 'kitchen', 'permission' => 'settings.view', 'restaurant_only' => true],
                ['label' => 'Backup & Data', 'route' => 'backup.index', 'active' => 'backup.*', 'icon' => 'download', 'permission' => 'settings.manage'],
            ],
        ],
    ],

];
