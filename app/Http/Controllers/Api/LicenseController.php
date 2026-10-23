<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Organization;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class LicenseController extends Controller
{
    /**
     * POST /api/license/register
     *
     * Called by a fresh desktop installation. Creates a new organization with
     * a 14-day trial and returns the license key the desktop stores locally.
     */
    public function register(Request $request): JsonResponse
    {
        $data = $request->validate([
            'business_name' => ['required', 'string', 'max:150'],
            'owner_name' => ['required', 'string', 'max:100'],
            'email' => ['required', 'email', 'max:191', 'unique:users,email'],
            'phone' => ['nullable', 'string', 'max:30'],
            'machine_id' => ['nullable', 'string', 'max:64'],
        ]);

        // Block if this machine already has a trial (even under a different email)
        if (! empty($data['machine_id'])) {
            $existing = Organization::where('machine_id', $data['machine_id'])->first();
            if ($existing) {
                return response()->json([
                    'error' => 'device_already_registered',
                    'message' => 'This device already has a trial account. Your trial may have expired — please purchase a license to continue.',
                ], 409);
            }
        }

        $organization = DB::transaction(function () use ($data): Organization {
            $slug = Str::slug($data['business_name']);
            $base = $slug;
            $i = 1;
            while (Organization::where('slug', $slug)->exists()) {
                $slug = "{$base}-{$i}";
                $i++;
            }

            $org = Organization::create([
                'name' => $data['business_name'],
                'slug' => $slug,
                'phone' => $data['phone'] ?? null,
                'plan' => 'trial',
                'status' => 'trial',
                'trial_ends_at' => now()->addDays(14)->toDateString(),
                'machine_id' => $data['machine_id'] ?? null,
            ]);

            $password = Str::random(12);

            $user = User::create([
                'name' => $data['owner_name'],
                'email' => $data['email'],
                'password' => Hash::make($password),
                'organization_id' => $org->id,
                'is_org_owner' => true,
            ]);

            $user->assignRole('Admin');

            return $org;
        });

        return response()->json([
            'license_key' => $organization->license_key,
            'status' => 'trial',
            'trial_ends_at' => $organization->trial_ends_at->toDateString(),
            'days_remaining' => $organization->daysRemaining(),
            'message' => 'Trial started! You have 14 days to try SahulatPOS.',
        ], 201);
    }

    /**
     * GET /api/license/status?key={license_key}
     *
     * Called on every desktop app launch to check if access is still allowed.
     */
    public function status(Request $request): JsonResponse
    {
        $key = $request->query('key');

        if (blank($key)) {
            return response()->json(['error' => 'Missing license key.'], 422);
        }

        $org = Organization::where('license_key', $key)->first();

        if (! $org) {
            return response()->json(['error' => 'License key not found.'], 404);
        }

        // Validate machine_id if org has one locked in
        $requestMachineId = $request->query('machine_id');
        if ($org->machine_id && $requestMachineId && $org->machine_id !== $requestMachineId) {
            return response()->json([
                'error' => 'machine_mismatch',
                'message' => 'This license is registered to a different device. Contact support if you changed hardware.',
            ], 403);
        }

        // Lock machine_id on first status check (if org has none yet)
        if (! $org->machine_id && $requestMachineId) {
            $org->update(['machine_id' => $requestMachineId]);
        }

        $isActive = $org->isActive();
        $days = $org->daysRemaining();

        $status = match ($org->status) {
            'trial' => $isActive ? 'trial' : 'expired',
            'active' => $isActive ? 'active' : 'expired',
            'suspended' => 'suspended',
            default => 'expired',
        };

        return response()->json([
            'status' => $status,
            'days_remaining' => max(0, $days),
            'plan' => $org->plan,
            'business_name' => $org->name,
            'message' => $this->statusMessage($status, $days),
        ]);
    }

    private function statusMessage(string $status, int $days): string
    {
        return match ($status) {
            'trial' => "Trial active — {$days} day(s) remaining.",
            'active' => "Subscription active — {$days} day(s) remaining.",
            'expired' => 'Your subscription has expired. Please renew to continue.',
            'suspended' => 'Account suspended. Please contact support.',
            default => 'Unknown status.',
        };
    }
}
