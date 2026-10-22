@extends('admin.layouts.app')
@section('title', $organization->name)
@section('heading', $organization->name)

@section('content')

<div class="max-w-2xl space-y-6">

    <a href="{{ route('admin.organizations.index') }}"
       class="inline-flex items-center gap-1 text-sm text-gray-500 hover:text-gray-700">
        ← Back to all tenants
    </a>

    {{-- Subscription info --}}
    <div class="bg-white rounded-xl border border-gray-200 p-6">
        <h2 class="font-semibold text-gray-800 mb-4">Subscription Details</h2>
        <dl class="grid grid-cols-2 gap-4 text-sm">
            <div>
                <dt class="text-gray-500">Status</dt>
                <dd class="font-medium capitalize mt-0.5">
                    @php
                        $badge = match($organization->status) {
                            'active'    => 'bg-green-100 text-green-800',
                            'trial'     => 'bg-blue-100 text-blue-800',
                            'suspended' => 'bg-red-100 text-red-800',
                            default     => 'bg-gray-100 text-gray-700',
                        };
                    @endphp
                    <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium {{ $badge }}">
                        {{ ucfirst($organization->status) }}
                    </span>
                </dd>
            </div>
            <div>
                <dt class="text-gray-500">Plan</dt>
                <dd class="font-medium capitalize mt-0.5">{{ $organization->plan }}</dd>
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
                    <option value="annual">Annual — PKR 24,990 (adds 12 months)</option>
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
