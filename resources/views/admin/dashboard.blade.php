@extends('admin.layouts.app')
@section('title', 'Dashboard')
@section('heading', 'Dashboard')

@section('content')

{{-- Stat cards --}}
<div class="grid grid-cols-2 lg:grid-cols-4 gap-5 mb-8">
    <div class="bg-white rounded-xl border border-gray-200 p-5">
        <div class="text-sm text-gray-500 mb-1">Total Tenants</div>
        <div class="text-3xl font-bold text-gray-900">{{ $stats['total'] }}</div>
    </div>
    <div class="bg-white rounded-xl border border-gray-200 p-5">
        <div class="text-sm text-gray-500 mb-1">Active</div>
        <div class="text-3xl font-bold text-green-600">{{ $stats['active'] }}</div>
    </div>
    <div class="bg-white rounded-xl border border-gray-200 p-5">
        <div class="text-sm text-gray-500 mb-1">On Trial</div>
        <div class="text-3xl font-bold text-blue-600">{{ $stats['trial'] }}</div>
    </div>
    <div class="bg-white rounded-xl border border-gray-200 p-5">
        <div class="text-sm text-gray-500 mb-1">Suspended</div>
        <div class="text-3xl font-bold text-red-500">{{ $stats['suspended'] }}</div>
    </div>
</div>

@if ($stats['expiring'] > 0)
    <div class="mb-6 px-4 py-3 bg-amber-50 border border-amber-200 rounded-lg text-amber-800 text-sm">
        ⚠️ <strong>{{ $stats['expiring'] }}</strong> subscription(s) expiring within 7 days.
        <a href="{{ route('admin.organizations.index') }}" class="underline ml-1">View all</a>
    </div>
@endif

{{-- Recent tenants --}}
<div class="bg-white rounded-xl border border-gray-200">
    <div class="px-6 py-4 border-b border-gray-100 flex items-center justify-between">
        <h2 class="font-semibold text-gray-800">Recent Tenants</h2>
        <a href="{{ route('admin.organizations.index') }}"
           class="text-sm text-indigo-600 hover:underline">View all →</a>
    </div>
    <table class="min-w-full text-sm">
        <thead class="bg-gray-50">
            <tr>
                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Business</th>
                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Plan</th>
                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Status</th>
                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Expires</th>
                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Users</th>
                <th class="px-6 py-3"></th>
            </tr>
        </thead>
        <tbody class="divide-y divide-gray-100">
            @forelse ($recent as $org)
                <tr class="hover:bg-gray-50">
                    <td class="px-6 py-4 font-medium text-gray-900">{{ $org->name }}</td>
                    <td class="px-6 py-4 capitalize text-gray-600">{{ $org->plan }}</td>
                    <td class="px-6 py-4">
                        @php
                            $badge = match($org->status) {
                                'active'    => 'bg-green-100 text-green-800',
                                'trial'     => 'bg-blue-100 text-blue-800',
                                'suspended' => 'bg-red-100 text-red-800',
                                default     => 'bg-gray-100 text-gray-700',
                            };
                        @endphp
                        <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium {{ $badge }}">
                            {{ ucfirst($org->status) }}
                        </span>
                    </td>
                    <td class="px-6 py-4 text-gray-600">
                        {{ ($org->status === 'trial' ? $org->trial_ends_at : $org->subscribed_until)?->format('d M Y') ?? '—' }}
                    </td>
                    <td class="px-6 py-4 text-gray-600">{{ $org->users_count }}</td>
                    <td class="px-6 py-4 text-right">
                        <a href="{{ route('admin.organizations.show', $org) }}"
                           class="text-indigo-600 hover:underline text-xs font-medium">Manage</a>
                    </td>
                </tr>
            @empty
                <tr>
                    <td colspan="6" class="px-6 py-10 text-center text-gray-400">No tenants yet.</td>
                </tr>
            @endforelse
        </tbody>
    </table>
</div>

@endsection
