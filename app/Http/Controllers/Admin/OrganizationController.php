<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Organization;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Super-admin panel: manage all tenants and their subscriptions.
 * Routes protected by the 'super-admin' role.
 */
class OrganizationController extends Controller
{
    public function index(): View
    {
        $organizations = Organization::query()
            ->withCount('users')
            ->latest()
            ->paginate(25);

        return view('admin.organizations.index', compact('organizations'));
    }

    public function show(Organization $organization): View
    {
        $organization->loadCount('users');

        return view('admin.organizations.show', compact('organization'));
    }

    /**
     * Extend the subscription (mark payment received).
     */
    public function extendSubscription(Request $request, Organization $organization): RedirectResponse
    {
        $data = $request->validate([
            'plan' => ['required', 'in:monthly,annual'],
            'plan_price' => ['nullable', 'numeric', 'min:0'],
            'notes' => ['nullable', 'string', 'max:500'],
        ]);

        $organization->extendSubscription($data['plan']);

        $organization->update(array_filter([
            'plan_price' => $data['plan_price'] ?? null,
            'notes' => $data['notes'] ?? null,
        ], fn ($v) => $v !== null));

        return back()->with('success', "Subscription extended for {$organization->name}.");
    }

    /**
     * Suspend a tenant (non-payment, abuse, etc.).
     */
    public function suspend(Organization $organization): RedirectResponse
    {
        $organization->update(['status' => 'suspended']);

        return back()->with('success', "{$organization->name} has been suspended.");
    }

    /**
     * Re-activate a suspended tenant.
     */
    public function unsuspend(Organization $organization): RedirectResponse
    {
        $organization->update(['status' => 'active']);

        return back()->with('success', "{$organization->name} has been reactivated.");
    }
}
