<?php

namespace App\Providers;

use App\Settings\Settings;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->singleton(Settings::class);
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // On the desktop build PHP's cli-server may not have a writable system
        // temp dir, so point file uploads at a path we know is writable.
        if (PHP_SAPI === 'cli-server') {
            $tmp = storage_path('app/tmp');
            if (! is_dir($tmp)) {
                mkdir($tmp, 0777, true);
            }
            ini_set('upload_tmp_dir', $tmp);
        }
    }
}
