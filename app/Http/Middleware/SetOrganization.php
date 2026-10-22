<?php

namespace App\Http\Middleware;

use App\Models\Organization;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\App;
use Symfony\Component\HttpFoundation\Response;

class SetOrganization
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if ($user && $user->organization_id) {
            $organization = $user->organization;

            // Block suspended organizations
            if ($organization && ! $organization->isActive()) {
                if ($request->expectsJson()) {
                    return response()->json(['message' => 'Subscription expired.'], 403);
                }

                return redirect()->route('subscription.expired');
            }

            // Share the current org everywhere (views, services, etc.)
            App::instance(Organization::class, $organization);
            app()->bind('current.organization', fn () => $organization);
        }

        return $next($request);
    }
}
