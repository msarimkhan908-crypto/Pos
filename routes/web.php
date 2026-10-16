<?php

use App\Http\Controllers\BackupController;
use App\Http\Controllers\BrandController;
use App\Http\Controllers\CategoryController;
use App\Http\Controllers\CustomerController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\DesktopController;
use App\Http\Controllers\ExpenseCategoryController;
use App\Http\Controllers\ExpenseController;
use App\Http\Controllers\HeldSaleController;
use App\Http\Controllers\KitchenOrderController;
use App\Http\Controllers\KitchenStationController;
use App\Http\Controllers\ModifierGroupController;
use App\Http\Controllers\OrderController;
use App\Http\Controllers\PosController;
use App\Http\Controllers\ProductController;
use App\Http\Controllers\ProductVariantController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\PurchaseController;
use App\Http\Controllers\ReportController;
use App\Http\Controllers\RestaurantTableController;
use App\Http\Controllers\ReturnController;
use App\Http\Controllers\RoleController;
use App\Http\Controllers\SaleController;
use App\Http\Controllers\SettingsController;
use App\Http\Controllers\StorageController;
use App\Http\Controllers\SupplierController;
use App\Http\Controllers\UserController;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return redirect()->route('dashboard');
});

// Serve public-disk files without requiring a storage:link symlink.
// On a standard web install the public/storage symlink takes precedence and
// this route is never reached. On desktop Windows it is the only image path.
Route::get('storage/{path}', [StorageController::class, 'serve'])
    ->where('path', '.+')
    ->name('storage.serve');

Route::get('/dashboard', DashboardController::class)
    ->middleware(['auth', 'verified'])
    ->name('dashboard');

Route::middleware('auth')->group(function () {
    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');
});

// Catalog. Permissions are enforced by each controller's middleware().
Route::middleware(['auth', 'verified'])->group(function () {
    Route::patch('categories/{category}/toggle', [CategoryController::class, 'toggle'])->name('categories.toggle');
    Route::resource('categories', CategoryController::class);

    Route::patch('brands/{brand}/toggle', [BrandController::class, 'toggle'])->name('brands.toggle');
    Route::resource('brands', BrandController::class);

    Route::patch('products/{product}/toggle', [ProductController::class, 'toggle'])->name('products.toggle');
    Route::resource('products', ProductController::class);
    Route::resource('products.variants', ProductVariantController::class)->except(['index', 'show'])->scoped();

    Route::resource('modifier-groups', ModifierGroupController::class)->except(['show']);
});

// Purchasing & customers. Permissions are enforced by each controller's middleware().
Route::middleware(['auth', 'verified'])->group(function () {
    Route::patch('suppliers/{supplier}/toggle', [SupplierController::class, 'toggle'])->name('suppliers.toggle');
    Route::resource('suppliers', SupplierController::class);

    Route::patch('purchases/{purchase}/status', [PurchaseController::class, 'updateStatus'])->name('purchases.status');
    Route::patch('purchases/{purchase}/payment-status', [PurchaseController::class, 'updatePaymentStatus'])->name('purchases.payment-status');
    Route::resource('purchases', PurchaseController::class);

    Route::patch('customers/{customer}/toggle', [CustomerController::class, 'toggle'])->name('customers.toggle');
    Route::resource('customers', CustomerController::class);
});

// POS / sales. Permissions are enforced by each controller's middleware().
Route::middleware(['auth', 'verified'])->group(function () {
    Route::get('pos', [PosController::class, 'index'])->name('pos.index');
    Route::get('pos/products', [PosController::class, 'products'])->name('pos.products');
    Route::get('pos/lookup', [PosController::class, 'lookup'])->name('pos.lookup');
    Route::post('pos/quote', [PosController::class, 'quote'])->name('pos.quote');
    Route::get('pos/tables', [PosController::class, 'tables'])->name('pos.tables');
    Route::get('pos/history', [PosController::class, 'recentSales'])->name('pos.history');

    Route::get('pos/held', [HeldSaleController::class, 'index'])->name('pos.held.index');
    Route::post('pos/held', [HeldSaleController::class, 'store'])->name('pos.held.store');
    Route::post('pos/held/{sale}/resume', [HeldSaleController::class, 'resume'])->name('pos.held.resume');
    Route::delete('pos/held/{sale}', [HeldSaleController::class, 'destroy'])->name('pos.held.destroy');

    Route::post('sales', [SaleController::class, 'store'])->name('sales.store');
    Route::get('sales/{sale}/receipt', [SaleController::class, 'receipt'])->name('sales.receipt');
    Route::get('sales/{sale}/print', [SaleController::class, 'print'])->name('sales.print');
    Route::get('sales/{sale}/edit', [SaleController::class, 'edit'])->name('sales.edit');
    Route::patch('sales/{sale}', [SaleController::class, 'update'])->name('sales.update');
});

// Restaurant & kitchen (Day 5). Permissions are enforced by each controller's middleware().
Route::middleware(['auth', 'verified'])->group(function () {
    Route::prefix('restaurant')->name('restaurant.')->group(function () {
        Route::patch('tables/{table}/status', [RestaurantTableController::class, 'updateStatus'])->name('tables.status');
        Route::resource('tables', RestaurantTableController::class)->except(['show']);
    });

    Route::get('kitchen', [KitchenOrderController::class, 'index'])->name('kitchen.index');
    Route::get('kitchen/orders', [KitchenOrderController::class, 'orders'])->name('kitchen.orders');
    Route::get('kitchen/orders/{kitchenOrder}', [KitchenOrderController::class, 'show'])->name('kitchen.orders.show');
    Route::post('kitchen/orders/{kitchenOrder}/status', [KitchenOrderController::class, 'updateStatus'])->name('kitchen.orders.status');

    Route::prefix('kitchen')->name('kitchen.')->group(function () {
        Route::resource('stations', KitchenStationController::class)->except(['show']);
    });
});

// Returns, expenses, reports and settings (Day 6). Permissions are enforced by each controller's middleware().
Route::middleware(['auth', 'verified'])->group(function () {
    Route::patch('returns/{return}/status', [ReturnController::class, 'updateStatus'])->name('returns.status');
    Route::resource('returns', ReturnController::class)->except(['edit', 'update']);

    Route::resource('expense-categories', ExpenseCategoryController::class)->except(['show']);
    Route::resource('expenses', ExpenseController::class);

    Route::prefix('reports')->name('reports.')->controller(ReportController::class)->group(function () {
        Route::get('/', 'index')->name('index');
        Route::get('sales', 'sales')->name('sales');
        Route::get('purchases', 'purchases')->name('purchases');
        Route::get('returns', 'returns')->name('returns');
        Route::get('expenses', 'expenses')->name('expenses');
        Route::get('customers', 'customers')->name('customers');
        Route::get('products', 'products')->name('products');
        Route::get('daily', 'daily')->name('daily');
    });

    Route::redirect('settings', 'settings/business')->name('settings.index');
    foreach (array_keys(SettingsController::SECTIONS) as $section) {
        Route::get("settings/{$section}", [SettingsController::class, 'show'])->defaults('section', $section)->name("settings.{$section}");
    }
    Route::put('settings/{section}', [SettingsController::class, 'update'])->whereIn('section', array_keys(SettingsController::SECTIONS))->name('settings.update');
    Route::post('settings/preview/{kind}', [SettingsController::class, 'preview'])->whereIn('kind', ['receipt', 'kot'])->name('settings.preview');
});

// Orders, users and role management. Permissions are enforced by each controller's middleware().
Route::middleware(['auth', 'verified'])->group(function () {
    Route::get('orders', [OrderController::class, 'index'])->name('orders.index');
    Route::get('orders/{order}', [OrderController::class, 'show'])->name('orders.show');

    Route::resource('users', UserController::class)->except(['show']);
    Route::resource('roles', RoleController::class)->except(['show']);
    Route::get('roles/{role}', [RoleController::class, 'show'])->name('roles.show');
});

// Backup
Route::middleware(['auth', 'verified'])->prefix('backup')->name('backup.')->group(function () {
    Route::get('/', [BackupController::class, 'index'])->name('index');
    Route::post('/', [BackupController::class, 'store'])->name('store');
    Route::get('download/{filename}', [BackupController::class, 'download'])->name('download');
    Route::delete('{filename}', [BackupController::class, 'destroy'])->name('destroy');
});

// Desktop sync (only active when DEVICE_ID is set in .env)
Route::middleware(['auth', 'verified'])->prefix('desktop')->name('desktop.')->group(function () {
    Route::get('sync-status', [DesktopController::class, 'syncStatus'])->name('sync-status');
    Route::post('sync', [DesktopController::class, 'sync'])->name('sync');
});

require __DIR__.'/auth.php';
