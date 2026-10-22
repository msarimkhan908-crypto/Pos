<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Organization;
use Illuminate\View\View;

class DashboardController extends Controller
{
    public function __invoke(): View
    {
        $stats = [
            'total' => Organization::count(),
            'active' => Organization::where('status', 'active')->count(),
            'trial' => Organization::where('status', 'trial')->count(),
            'suspended' => Organization::where('status', 'suspended')->count(),
            'expiring' => Organization::where('status', 'active')
                ->whereNotNull('subscribed_until')
                ->whereBetween('subscribed_until', [now(), now()->addDays(7)])
                ->count(),
        ];

        $recent = Organization::withCount('users')->latest()->limit(5)->get();

        return view('admin.dashboard', compact('stats', 'recent'));
    }
}
