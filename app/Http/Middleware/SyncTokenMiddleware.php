<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class SyncTokenMiddleware
{
    public function handle(Request $request, Closure $next): Response
    {
        $token = config('app.sync_token');

        if (blank($token)) {
            return response()->json(['error' => 'Sync not configured on this server.'], 503);
        }

        $provided = $request->bearerToken();

        if (! hash_equals($token, (string) $provided)) {
            return response()->json(['error' => 'Unauthorized.'], 401);
        }

        return $next($request);
    }
}
