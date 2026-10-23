@extends('admin.layouts.app')
@section('title', $organization->name)
@section('heading', $organization->name)

@section('content')

<div class="max-w-2xl space-y-6">

    <a href="{{ route('admin.organizations.index') }}"
       class="inline-flex items-center gap-1 text-sm text-gray-500 hover:text-gray-700">
        ← Back to all tenants
    </a>

    {{-- Payment proof pending banner --}}
    @if($organization->status === 'payment_submitted' && $organization->payment_proof_path)
        <div class="bg-amber-50 border border-amber-200 rounded-xl p-5">
            <div class="flex items-start gap-3 mb-4">
                <div class="w-8 h-8 rounded-full bg-amber-100 flex items-center justify-center flex-shrink-0 mt-0.5">
                    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" class="w-4 h-4 text-amber-600">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M12 9v3.75m-9.303 3.376c-.866 1.5.217 3.374 1.948 3.374h14.71c1.73 0 2.813-1.874 1.948-3.374L13.949 3.378c-.866-1.5-3.032-1.5-3.898 0L2.697 16.126ZM12 15.75h.007v.008H12v-.008Z" />
                    </svg>
                </div>
                <div>
                    <h3 class="font-semibold text-amber-800">Payment Proof Submitted</h3>
                    <p class="text-sm text-amber-700 mt-0.5">
                        This business registered for the <strong>{{ ucfirst($organization->pending_plan ?? 'paid') }}</strong> plan
                        and uploaded payment proof. Review the screenshot below and activate if payment is confirmed.
                    </p>
                </div>
            </div>

            {{-- Proof image / file --}}
            @php
                $proofExt = pathinfo($organization->payment_proof_path, PATHINFO_EXTENSION);
                $proofUrl = asset('storage/' . $organization->payment_proof_path);
            @endphp

            @if(in_array(strtolower($proofExt), ['jpg','jpeg','png']))
                <div class="rounded-lg overflow-hidden border border-amber-200 mb-4">
                    <img src="{{ $proofUrl }}" alt="Payment proof" class="w-full max-h-80 object-contain bg-white">
                </div>
            @elseif(strtolower($proofExt) === 'pdf')
                <div class="mb-4">
                    <a href="{{ $proofUrl }}" target="_blank"
                       class="inline-flex items-center gap-2 px-4 py-2 bg-amber-100 hover:bg-amber-200 text-amber-800 font-medium text-sm rounded-lg transition">
                        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="w-4 h-4">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M19.5 14.25v-2.625a3.375 3.375 0 0 0-3.375-3.375h-1.5A1.125 1.125 0 0 1 13.5 7.125v-1.5a3.375 3.375 0 0 0-3.375-3.375H8.25m2.25 0H5.625c-.621 0-1.125.504-1.125 1.125v17.25c0 .621.504 1.125 1.125 1.125h12.75c.621 0 1.125-.504 1.125-1.125V11.25a9 9 0 0 0-9-9Z" />
                        </svg>
                        Open Payment PDF
                    </a>
                </div>
            @endif

            {{-- Approve button --}}
            <form method="POST" action="{{ route('admin.organizations.extend', $organization) }}">
                @csrf
                <input type="hidden" name="plan" value="{{ $organization->pending_plan ?? 'monthly' }}">
                <input type="hidden" name="plan_price" value="{{ $organization->pending_plan === 'annual' ? 14999 : 2499 }}">
                <button type="submit"
                        onclick="return confirm('Confirm payment received and activate this account?')"
                        class="px-5 py-2.5 bg-green-600 hover:bg-green-700 text-white font-semibold text-sm rounded-lg transition shadow-sm">
                    ✓ Confirm Payment &amp; Activate Account
                </button>
            </form>
        </div>
    @endif

    @if (session('success'))
        <div class="bg-green-50 border border-green-200 rounded-lg px-4 py-3 text-sm text-green-800 font-medium">
            {{ session('success') }}
        </div>
    @endif

    {{-- Subscription info --}}
    <div class="bg-white rounded-xl border border-gray-200 p-6">
        <h2 class="font-semibold text-gray-800 mb-4">Subscription Details</h2>
        <dl class="grid grid-cols-2 gap-4 text-sm">
            <div>
                <dt class="text-gray-500">Status</dt>
                <dd class="font-medium capitalize mt-0.5">
                    @php
                        $badge = match($organization->status) {
                            'active'             => 'bg-green-100 text-green-800',
                            'trial'              => 'bg-blue-100 text-blue-800',
                            'payment_submitted'  => 'bg-amber-100 text-amber-800',
                            'suspended'          => 'bg-red-100 text-red-800',
                            default              => 'bg-gray-100 text-gray-700',
                        };
                        $statusLabel = match($organization->status) {
                            'payment_submitted' => 'Pending Activation',
                            default             => ucfirst($organization->status),
                        };
                    @endphp
                    <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium {{ $badge }}">
                        {{ $statusLabel }}
                    </span>
                </dd>
            </div>
            <div>
                <dt class="text-gray-500">Plan</dt>
                <dd class="font-medium capitalize mt-0.5">
                    {{ $organization->plan }}
                    @if($organization->pending_plan && $organization->status === 'payment_submitted')
                        <span class="ml-1 text-xs text-amber-600">(→ {{ $organization->pending_plan }} pending)</span>
                    @endif
                </dd>
            </div>
            <div>
                <dt class="text-gray-500">Trial ends</dt>
                <dd class="mt-0.5">{{ $organization->trial_ends_at?->format('d M Y') ?? '—' }}</dd>
            </div>
            <div>
                <dt class="text-gray-500">Subscribed until</dt>
                <dd class="mt-0.5">{{ $organization->subscribed_until?->format('d M Y') ?? '—' }}</dd>
            </div>
            <div>
                <dt class="text-gray-500">Days remaining</dt>
                <dd class="mt-0.5 font-medium {{ $organization->daysRemaining() < 7 ? 'text-red-600' : '' }}">
                    {{ $organization->daysRemaining() }} days
                </dd>
            </div>
            <div>
                <dt class="text-gray-500">Users</dt>
                <dd class="mt-0.5">{{ $organization->users_count }}</dd>
            </div>
            <div>
                <dt class="text-gray-500">Registered</dt>
                <dd class="mt-0.5">{{ $organization->created_at->format('d M Y') }}</dd>
            </div>
            <div>
                <dt class="text-gray-500">Phone</dt>
                <dd class="mt-0.5">{{ $organization->phone ?? '—' }}</dd>
            </div>
            <div>
                <dt class="text-gray-500">Last fee paid</dt>
                <dd class="mt-0.5 font-medium">
                    {{ $organization->plan_price ? 'PKR ' . number_format($organization->plan_price, 0) : '—' }}
                </dd>
            </div>
        </dl>
        @if ($organization->notes)
            <p class="mt-4 text-sm text-gray-600 italic border-t border-gray-100 pt-4">
                Note: {{ $organization->notes }}
            </p>
        @endif
    </div>

    {{-- Extend subscription --}}
    <div class="bg-white rounded-xl border border-gray-200 p-6">
        <h2 class="font-semibold text-gray-800 mb-4">Mark Payment Received</h2>
        <form method="POST" action="{{ route('admin.organizations.extend', $organization) }}" class="space-y-4">
            @csrf
            <div>
                <label for="plan" class="block text-sm font-medium text-gray-700 mb-1">Plan</label>
                <select id="plan" name="plan"
                        class="block w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:ring-indigo-500 focus:border-indigo-500">
                    <option value="monthly">Monthly — PKR 2,499 (adds 1 month)</option>
                    <option value="annual">Annual — PKR 14,999 (adds 12 months)</option>
                </select>
            </div>
            <div>
                <label for="plan_price" class="block text-sm font-medium text-gray-700 mb-1">Amount Received (PKR)</label>
                <input id="plan_price" name="plan_price" type="number" min="0" step="1"
                       value="{{ old('plan_price', $organization->plan_price) }}"
                       placeholder="e.g. 2499"
                       class="block w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:ring-indigo-500 focus:border-indigo-500">
            </div>
            <div>
                <label for="notes" class="block text-sm font-medium text-gray-700 mb-1">Notes (optional)</label>
                <input id="notes" name="notes" type="text"
                       placeholder="e.g. Bank transfer ref #123"
                       class="block w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:ring-indigo-500 focus:border-indigo-500">
            </div>
            <button type="submit"
                    class="px-4 py-2 bg-indigo-600 hover:bg-indigo-700 text-white text-sm font-medium rounded-lg transition">
                Extend Subscription
            </button>
        </form>
    </div>

    {{-- Suspend / unsuspend --}}
    <div class="bg-white rounded-xl border border-gray-200 p-6">
        <h2 class="font-semibold text-gray-800 mb-4">Account Actions</h2>
        @if ($organization->status === 'suspended')
            <form method="POST" action="{{ route('admin.organizations.unsuspend', $organization) }}">
                @csrf
                <button type="submit"
                        class="px-4 py-2 bg-green-600 hover:bg-green-700 text-white text-sm font-medium rounded-lg transition">
                    Reactivate Account
                </button>
            </form>
        @else
            <form method="POST" action="{{ route('admin.organizations.suspend', $organization) }}">
                @csrf
                <button type="submit"
                        onclick="return confirm('Suspend {{ addslashes($organization->name) }}? They will lose access immediately.')"
                        class="px-4 py-2 bg-red-600 hover:bg-red-700 text-white text-sm font-medium rounded-lg transition">
                    Suspend Account
                </button>
            </form>
        @endif
    </div>

</div>

@endsection
