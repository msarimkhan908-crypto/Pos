<?php

use App\Http\Controllers\Api\LicenseController;
use App\Http\Controllers\SyncController;
use App\Http\Middleware\SyncTokenMiddleware;
use Illuminate\Support\Facades\Route;

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
