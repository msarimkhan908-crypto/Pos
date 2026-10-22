<?php

namespace App\Http\Middleware;

use App\Models\Organization;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class SyncTokenMiddleware
{
    public function handle(Request $request, Closure $next): Response
    {
        $provided = (string) $request->bearerToken();

        if (blank($provided)) {
            return response()->json(['error' => 'Unauthorized.'], 401);
        }

        // Accept the global server-side sync token (desktop .env)
        $globalToken = config('app.sync_token');
        if (filled($globalToken) && hash_equals($globalToken, $provided)) {
            return $next($request);
        }

        // Accept any active organization's license key
        $org = Organization::where('license_key', $provided)->first();
        if ($org && $org->isActive()) {
            $request->attributes->set('sync_organization', $org);

            return $next($request);
        }

        return response()->json(['error' => 'Unauthorized.'], 401);
    }
}
