# Roles, Permissions & Demo Users

Access control for the POS uses [Spatie Laravel Permission](https://spatie.be/docs/laravel-permission) (v8) on the `web` guard. Roles are sets of permissions; users are given one or more roles.

## Quick start

```powershell
php artisan migrate
php artisan db:seed --class=RolesAndPermissionsSeeder   # roles + permissions
php artisan db:seed --class=DemoUsersSeeder             # one login per role (non-production only)
```

`php artisan db:seed` runs both, then creates a factory `test@example.com` user. On a database that already has that user, run the two seeders individually as above.

Both seeders are idempotent and safe to re-run.

## Demo accounts

Created by `DemoUsersSeeder` for local testing. **Skipped automatically when `APP_ENV=production`.**

| Role | Email | Password |
|---|---|---|
| Super Admin | superadmin@pos.test | password |
| Admin | admin@pos.test | password |
| Manager | manager@pos.test | password |
| Cashier | cashier@pos.test | password |
| Kitchen Staff | kitchen@pos.test | password |

All demo accounts are marked email-verified, so they keep working if email verification is enforced later. Re-running the seeder resets their name, password and role.

For what each role sees on the dashboard, see [dashboard.md](dashboard.md). For catalog pages, see [products.md](products.md). For suppliers, purchases and customers, see [purchasing-and-customers.md](purchasing-and-customers.md). For the POS screen, see [pos-and-sales.md](pos-and-sales.md) (`sales.create` to sell, `sales.view` for receipts). For tables and the kitchen, see [restaurant-and-kitchen.md](restaurant-and-kitchen.md). For returns and expenses see [returns-and-expenses.md](returns-and-expenses.md), for reports [reports.md](reports.md), for settings and printing [settings-and-printing.md](settings-and-printing.md) (`settings.manage` is the "edit settings" permission).

The `inventory.*` permissions were removed together with the inventory module on 2026-09-24. The seeder deletes permissions that are no longer defined.

## Roles

| Role | Permissions | Responsibility |
|---|---|---|
| Super Admin | all 54 | Full control, including creating, editing and deleting roles |
| Admin | 51 | Runs the business; everything except creating, editing and deleting roles |
| Manager | 36 | Day-to-day store operations, including catalog edits, suppliers, purchases, tables, returns and expenses; no deletes, no user management, read-only settings |
| Cashier | 1 | POS only: ring up sales and print receipts. No other pages or modules. |
| Kitchen Staff | 3 | Kitchen display: view products, view and update kitchen orders |

## Permission matrix

| Permission | Super Admin | Admin | Manager | Cashier | Kitchen Staff |
|---|:-:|:-:|:-:|:-:|:-:|
| users.view | ✓ | ✓ | ✓ | | |
| users.create | ✓ | ✓ | | | |
| users.edit | ✓ | ✓ | | | |
| users.delete | ✓ | ✓ | | | |
| roles.view | ✓ | ✓ | | | |
| roles.create | ✓ | | | | |
| roles.edit | ✓ | | | | |
| roles.delete | ✓ | | | | |
| categories.view | ✓ | ✓ | ✓ | | |
| categories.create | ✓ | ✓ | ✓ | | |
| categories.edit | ✓ | ✓ | ✓ | | |
| categories.delete | ✓ | ✓ | | | |
| brands.view | ✓ | ✓ | ✓ | | |
| brands.create | ✓ | ✓ | ✓ | | |
| brands.edit | ✓ | ✓ | ✓ | | |
| brands.delete | ✓ | ✓ | | | |
| products.view | ✓ | ✓ | ✓ | | ✓ |
| products.create | ✓ | ✓ | ✓ | | |
| products.edit | ✓ | ✓ | ✓ | | |
| products.delete | ✓ | ✓ | | | |
| sales.view | ✓ | ✓ | ✓ | | |
| sales.create | ✓ | ✓ | ✓ | ✓ | |
| sales.edit | ✓ | ✓ | ✓ | | |
| sales.delete | ✓ | ✓ | | | |
| customers.view | ✓ | ✓ | ✓ | | |
| customers.create | ✓ | ✓ | ✓ | | |
| customers.edit | ✓ | ✓ | ✓ | | |
| customers.delete | ✓ | ✓ | | | |
| suppliers.view | ✓ | ✓ | ✓ | | |
| suppliers.create | ✓ | ✓ | ✓ | | |
| suppliers.edit | ✓ | ✓ | ✓ | | |
| suppliers.delete | ✓ | ✓ | | | |
| purchases.view | ✓ | ✓ | ✓ | | |
| purchases.create | ✓ | ✓ | ✓ | | |
| purchases.edit | ✓ | ✓ | ✓ | | |
| purchases.delete | ✓ | ✓ | | | |
| reports.view | ✓ | ✓ | ✓ | | |
| settings.view | ✓ | ✓ | ✓ | | |
| settings.manage | ✓ | ✓ | | | |
| kitchen.view | ✓ | ✓ | ✓ | | ✓ |
| kitchen.manage | ✓ | ✓ | ✓ | | ✓ |
| tables.view | ✓ | ✓ | ✓ | | |
| tables.create | ✓ | ✓ | ✓ | | |
| tables.edit | ✓ | ✓ | ✓ | | |
| tables.delete | ✓ | ✓ | | | |
| tables.status | ✓ | ✓ | ✓ | | |
| returns.view | ✓ | ✓ | ✓ | | |
| returns.create | ✓ | ✓ | ✓ | | |
| returns.edit | ✓ | ✓ | ✓ | | |
| returns.delete | ✓ | ✓ | | | |
| expenses.view | ✓ | ✓ | ✓ | | |
| expenses.create | ✓ | ✓ | ✓ | | |
| expenses.edit | ✓ | ✓ | ✓ | | |
| expenses.delete | ✓ | ✓ | | | |

To print the live matrix from the database:

```powershell
php artisan permission:show
```

## Sidebar visibility

Each sidebar item requires one permission (defined in `config/navigation.php`). Sections with no visible items are hidden. All routes are now implemented; there are no remaining "Soon" placeholders.

| Item | Permission | Visible to |
|---|---|---|
| Dashboard | none | everyone |
| POS | sales.create | Super Admin, Admin, Manager, Cashier |
| Orders | sales.view | Super Admin, Admin, Manager |
| Customers | customers.view | Super Admin, Admin, Manager |
| Returns | returns.view | Super Admin, Admin, Manager |
| Products, Modifiers | products.view | Super Admin, Admin, Manager, Kitchen Staff |
| Categories | categories.view | Super Admin, Admin, Manager |
| Brands | brands.view | Super Admin, Admin, Manager |
| Suppliers | suppliers.view | Super Admin, Admin, Manager |
| Purchases | purchases.view | Super Admin, Admin, Manager |
| Tables | tables.view | Super Admin, Admin, Manager |
| Kitchen Orders (kitchen display) | kitchen.view | Super Admin, Admin, Manager, Kitchen Staff |
| Expenses | expenses.view | Super Admin, Admin, Manager |
| Reports | reports.view | Super Admin, Admin, Manager |
| Users | users.view | Super Admin, Admin |
| Roles & Permissions | roles.view | Super Admin, Admin |
| All Settings pages, incl. Kitchen Stations | settings.view | Super Admin, Admin, Manager |

Cashier sees only **Dashboard** and **POS**. A user with no role sees only Dashboard.

## Common tasks

**Assign a role to an existing user**

```powershell
php artisan tinker --execute="App\Models\User::where('email', 'someone@example.com')->first()->assignRole('Manager');"
```

`assignRole()` adds a role; `syncRoles(['Manager'])` replaces all roles; `removeRole('Manager')` removes one.

**Change what a role can do**

1. Edit `ROLE_PERMISSIONS` in `database/seeders/RolesAndPermissionsSeeder.php`.
2. Run `php artisan db:seed --class=RolesAndPermissionsSeeder`.
3. Update the matrix in this document.

**Add a new permission**

1. Add the action to `PERMISSIONS` in `RolesAndPermissionsSeeder` (e.g. `'discounts' => ['view', 'apply']`).
2. Grant it to roles in `ROLE_PERMISSIONS`. Super Admin receives every permission automatically.
3. Re-run the seeder. The tests count permissions from the seeder, so no test change is needed.

**Protect a route**

```php
Route::middleware(['auth', 'permission:reports.view'])->get('/reports/sales', ...);
Route::middleware(['auth', 'role:Super Admin|Admin'])->group(function () { ... });
```

The `role`, `permission` and `role_or_permission` middleware aliases are registered in `bootstrap/app.php`.

**Check in code or Blade**

```php
$user->can('sales.create');
$user->hasRole('Cashier');
```

```blade
@can('products.create') ... @endcan
@role('Super Admin') ... @endrole
```

**Clear the permission cache** (if changes don't show up)

```powershell
php artisan permission:cache-reset
```

## Files

| File | Purpose |
|---|---|
| `config/permission.php` | Spatie configuration (defaults: `web` guard, no teams, 24h cache) |
| `database/seeders/RolesAndPermissionsSeeder.php` | Source of truth for roles and permissions |
| `database/seeders/DemoUsersSeeder.php` | Demo login per role |
| `app/Models/User.php` | Uses the `HasRoles` trait |
| `bootstrap/app.php` | Middleware aliases |
| `config/navigation.php` | Sidebar items and their required permissions |
| `tests/Feature/RolesAndPermissionsTest.php` | Role/permission tests |
| `tests/Feature/AppLayoutTest.php` | Sidebar visibility tests |
