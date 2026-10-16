<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Applies the business timezone (Settings → General) to every web request, so "today",
 * receipt times and report date ranges follow the store's local day.
 */
class ApplySettings
{
    public function handle(Request $request, Closure $next): Response
    {
        $timezone = settings('general.timezone');

        if ($timezone && in_array($timezone, timezone_identifiers_list(), true)) {
            config(['app.timezone' => $timezone]);
            date_default_timezone_set($timezone);
        }

        return $next($request);
    }
}
