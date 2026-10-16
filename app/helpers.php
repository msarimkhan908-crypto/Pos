<?php

use App\Settings\Settings;

if (! function_exists('settings')) {
    /**
     * settings() returns the settings store; settings('key') returns one value.
     */
    function settings(?string $key = null, mixed $default = null): mixed
    {
        $settings = app(Settings::class);

        return $key === null ? $settings : $settings->get($key, $default);
    }
}
