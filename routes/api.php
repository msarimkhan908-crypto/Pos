<?php

use App\Http\Controllers\SyncController;
use App\Http\Middleware\SyncTokenMiddleware;
use Illuminate\Support\Facades\Route;

Route::middleware([SyncTokenMiddleware::class])->prefix('sync')->group(function () {
    Route::get('catalog', [SyncController::class, 'catalog']);
    Route::post('sales', [SyncController::class, 'receiveSales']);
});
