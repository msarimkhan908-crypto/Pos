<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\Organization;
use App\Models\User;
use Illuminate\Auth\Events\Registered;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class RegisteredUserController extends Controller
{
    public function create(Request $request): View
    {
        $plan = in_array($request->query('plan'), ['trial', 'monthly', 'annual'], true)
            ? $request->query('plan')
            : 'trial';

        return view('auth.register', compact('plan'));
    }

    /**
     * Create a new organization and its owner account in one transaction.
     *
     * @throws ValidationException
     */
    public function store(Request $request): RedirectResponse
    {
        $plan = in_array($request->input('plan'), ['trial', 'monthly', 'annual'], true)
            ? $request->input('plan')
            : 'trial';

        $isPaidPlan = in_array($plan, ['monthly', 'annual'], true);

        $rules = [
            'business_name' => ['required', 'string', 'max:150'],
            'business_type' => ['required', Rule::in(array_keys(Organization::BUSINESS_TYPES))],
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'lowercase', 'email', 'max:255', 'unique:'.User::class],
            'password' => ['required', 'confirmed', Rules\Password::defaults()],
            'plan' => ['required', 'in:trial,monthly,annual'],
        ];

        if ($isPaidPlan) {
            $rules['payment_proof'] = ['required', 'file', 'mimes:jpg,jpeg,png,pdf', 'max:5120'];
        }

        $request->validate($rules);

        DB::transaction(function () use ($request, $plan, $isPaidPlan): void {
            $slug = Str::slug($request->business_name);
            $baseSlug = $slug;
            $i = 1;

            while (Organization::where('slug', $slug)->exists()) {
                $slug = $baseSlug.'-'.$i++;
            }

            $proofPath = null;
            if ($isPaidPlan && $request->hasFile('payment_proof')) {
                $proofPath = $request->file('payment_proof')->store('payment-proofs', 'public');
            }

            $organization = Organization::create([
                'name' => $request->business_name,
                'slug' => $slug,
                'business_type' => $request->business_type,
                'plan' => 'trial',
                'pending_plan' => $isPaidPlan ? $plan : null,
                'status' => $isPaidPlan ? 'payment_submitted' : 'trial',
                'trial_ends_at' => $isPaidPlan ? null : now()->addDays(4),
                'payment_proof_path' => $proofPath,
            ]);

            $user = User::create([
                'name' => $request->name,
                'email' => $request->email,
                'password' => Hash::make($request->password),
                'organization_id' => $organization->id,
                'is_org_owner' => true,
            ]);

            $user->assignRole('Admin');

            event(new Registered($user));

            Auth::login($user);
        });

        return redirect(route('dashboard', absolute: false));
    }
}
