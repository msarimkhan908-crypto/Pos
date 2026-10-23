<?php

use App\Http\Controllers\Api\BarcodeLookupController;
use App\Http\Controllers\Api\LicenseController;
use App\Http\Controllers\SyncController;
use App\Http\Middleware\SyncTokenMiddleware;
use Illuminate\Support\Facades\Route;

// Barcode product lookup — requires authenticated session
Route::middleware('auth')->get('barcode-lookup', BarcodeLookupController::class)->name('api.barcode-lookup');

// Desktop license API — open, no auth required
Route::prefix('license')->group(function () {
    Route::post('register', [LicenseController::class, 'register'])->name('api.license.register');
    Route::get('status', [LicenseController::class, 'status'])->name('api.license.status');
});

// Data sync — requires either the global sync token or a valid license key
Route::middleware([SyncTokenMiddleware::class])->prefix('sync')->group(function () {
    Route::get('catalog', [SyncController::class, 'catalog']);
    Route::post('sales', [SyncController::class, 'receiveSales']);
});
