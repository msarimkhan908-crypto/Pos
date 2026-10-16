# Testing

The test suite uses [Pest](https://pestphp.com) 5 on Laravel 13.

```powershell
php artisan test                        # full suite
php artisan test --filter=Dashboard     # one file or test name
composer test                           # clears config cache first, then runs the suite
```

## Database safety

Tests never touch the MySQL development database.

- `phpunit.xml` sets `DB_CONNECTION=sqlite` and `DB_DATABASE=:memory:`, so every test run starts from an empty in-memory database.
- `tests/Pest.php` applies `RefreshDatabase` to every Feature test. Migrations run fresh for each test, and nothing persists afterwards.
- **Guard:** `Tests\TestCase::ensureTestingDatabase()` runs before any trait (including `RefreshDatabase`) and throws if the connection is not in-memory SQLite. This protects against a cached config (`php artisan config:cache`), which would otherwise point tests at MySQL and let `RefreshDatabase` wipe it. If you see *"Refusing to run tests against database…"*, run `php artisan config:clear`.
- Tests that need roles seed them with `$this->seed(RolesAndPermissionsSeeder::class)` inside the test database only.

## What is covered

| File | Covers |
|---|---|
| `Feature/Auth/*Test.php` (Breeze) | Login, registration, logout, email verification, password reset, confirmation and update |
| `Feature/Auth/AuthFlowTest.php` | Register → logout → login journey, new users get no role, guest and logged-out redirects, logged-in users redirected away from login and register |
| `Feature/DashboardTest.php` | Auth protection, widgets per role, empty states, range switcher, rendering with real data (mocked service) |
| `Feature/RolesAndPermissionsTest.php` | Roles and permissions exist, seeder idempotent, Super Admin has every permission, per-role grants, assigning and removing roles, direct permissions, unknown permissions denied, route middleware |
| `Feature/AppLayoutTest.php` | App shell, sidebar sections and links per permission, "Soon" placeholders, links appear once routes exist |
| `Feature/BladeComponentsTest.php` | `x-ui.*` components, `x-dashboard.stat-card`, `x-icon`: variants, validation errors, accessibility attributes, password values never echoed |
| `Feature/ProfileTest.php` (Breeze) | Profile update and account deletion |
| `Feature/TestEnvironmentTest.php` | Tests use in-memory SQLite; the database guard refuses other connections |
| `Feature/Catalog/CategoryTest.php` | Category CRUD, slugs, validation, images, permissions, delete protection, search and filter |
| `Feature/Catalog/BrandTest.php` | Brand CRUD, logo, uniqueness, permissions, delete protection |
| `Feature/Catalog/ProductTest.php` | Product creation, no stock controls on product pages, validation, SKU and barcode uniqueness across products and variants, image validation by real content, relationships, search, filters, pagination, archive rules, permissions |
| `Feature/Catalog/ProductTypeTest.php` | Simple and variant product types: inline variants on create and edit, validation, codes checked within the form, switching type, SKU and barcode swaps |
| `Feature/Catalog/ProductVariantTest.php` | Variants: adding to an existing simple product, price inheritance, name, SKU and barcode rules, archiving, scoped URLs |
| `Feature/Catalog/ModifierGroupTest.php` | Groups and options, attaching to products, add-on price maths, sync (rename, swap, add, remove), validation, permissions |
| `Feature/Purchasing/SupplierTest.php` | Supplier CRUD, validation, status toggle, delete protection, permissions, search, filter, pagination |
| `Feature/Purchasing/PurchaseTest.php` | Purchases with multiple items, server-side totals (browser totals ignored), numbering and uniqueness, validation, variant rules, transaction rollback, status transitions, payment status, permissions, history search and filters, details page, no inventory side effects |
| `Feature/Customers/CustomerTest.php` | Customer CRUD, validation, deletion permissions, search, filter, pagination, dashboard count |
| `Feature/Pos/PosTest.php` | POS access per role; product grid, search (name, SKU, barcode), categories, barcode lookup and "Product not found."; server pricing (browser totals ignored); item and order discounts; per-product tax; variants and add-ons; customer; cash, card and mixed payments; insufficient cash; reference sale saved exactly (INV-000001, 1652, change 348); invoice numbering; snapshots; transaction rollback; no stock side effects; hold, resume and remove; receipts; customer delete protection; typed customer details, delivery fee, "save as new customer" |
| `Feature/Returns/ReturnTest.php` | Return form, refund = what was paid (to the cent, adds up per line), browser amounts ignored, quantity limits, fully returned items, invalid input, held sales refused, pending / complete / cancel / delete rules, numbering and prefix, permissions per role, history search and filters, details page, no stock side effects |
| `Feature/Expenses/ExpenseTest.php` | Expense creation and numbering (prefix from settings), protected fields, validation, inactive categories, edit and delete, permissions, search, filters and total, pagination, categories CRUD and delete protection, no automatic data |
| `Feature/Reports/ReportTest.php` | Overview figures from real data for each period (today, yesterday, this week, this month, last month, custom), empty states, sales, purchase, returns, expense, customer and product reports with filters and totals, CSV export, permissions, period validation, date range presets, dashboard figures |
| `Feature/Settings/SettingsTest.php` | Every settings section, view vs manage permissions, business info and logo upload / removal, upload validation (type, size, pixels), general settings (currency symbol, formats, timezone, prefixes), appearance CSS variables, default tax and label, dashboard widgets, printing settings, receipt and KOT settings shown on printouts, live previews with sample data only, print pages without app navigation |
| `Feature/Pos/PrintTest.php` | Checkout prints the customer bill and the kitchen bill as separate pages in one job, reprinting either one, retail orders print only the bill, access rules, POS print settings |
| `Feature/Restaurant/KitchenTest.php` | Dine In → sale → KOT (table occupied, KOT pending, items with stations and notes), kitchen feed, Pending → Preparing → Ready → Completed, forward-only statuses, station screens, status filters, printable KOT, take away / delivery, table rules (required, inactive, reserved, occupied), retail items skipped, station override, KOT numbering, multiple KOTs per sale, rollback, no stock change, held sales, receipt, permissions, POS table picker |
| `Feature/Restaurant/RestaurantTest.php` | Tables CRUD, validation, delete protection, status changes (HTML and JSON), per-role access, status counts; kitchen stations CRUD and permissions; station assignment on categories and products; sidebar links; `RestaurantSeeder` |
| `Unit/SaleCalculatorTest.php` | Sale maths: reference sale, fixed and % discounts, order discount before tax, clamping, per-product tax, exact cent allocation, no float drift, half-up rounding |
| `Unit/PurchaseCalculatorTest.php` | Exact decimal maths: line totals, sums, rounding, no float drift, large amounts |
| `Feature/Catalog/QueryCountTest.php` | N+1 guard: list pages and the product page run the same number of queries for 2 or 12 rows |

## Writing tests

- Put tests that touch the database or HTTP in `tests/Feature` (they get `RefreshDatabase` automatically).
- Create users with `User::factory()`. The default password is `password`.
- To test a role, seed roles first, then use the global helper `userWith('Cashier')` from `tests/Pest.php`.
- Factories exist for Category, Brand, Product, ProductVariant, ModifierGroup, Modifier, Supplier, Customer, Purchase, RestaurantTable and KitchenStation.
- Uploads: `UploadedFile::fake()` reports its MIME type from the **filename**. To test content sniffing, build a real `new UploadedFile($tempPath, 'name.jpg', null, null, true)`, as `realUpload()` in `ProductTest.php` does.
- Render components with `$this->blade('<x-ui.button>Save</x-ui.button>')`. To test validation errors, share them first: `view()->share('errors', $bag)`.
- **Blade gotcha:** always put whitespace after a closing named slot. `</x-slot:actions>Body` compiles to `@endslotBody`, which Blade doesn't recognise, so the slot is never closed and `@endslotBody` prints as text.
