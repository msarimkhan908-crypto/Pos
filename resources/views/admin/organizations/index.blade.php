@extends('admin.layouts.app')
@section('title', 'Organizations')
@section('heading', 'All Tenants')

@section('content')

<div class="bg-white rounded-xl border border-gray-200">
    <table class="min-w-full text-sm">
        <thead class="bg-gray-50">
            <tr>
                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Business</th>
                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Owner</th>
                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Plan</th>
                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Status</th>
                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Expires</th>
                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Users</th>
                <th class="px-6 py-3"></th>
            </tr>
        </thead>
        <tbody class="divide-y divide-gray-100">
            @forelse ($organizations as $org)
                <tr class="hover:bg-gray-50">
                    <td class="px-6 py-4 font-medium text-gray-900">{{ $org->name }}</td>
                    <td class="px-6 py-4 text-gray-600">{{ $org->owner()?->email ?? '—' }}</td>
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
                           class="text-indigo-600 hover:underline text-xs font-medium">Manage →</a>
                    </td>
                </tr>
            @empty
                <tr>
                    <td colspan="7" class="px-6 py-10 text-center text-gray-400">No tenants yet.</td>
                </tr>
            @endforelse
        </tbody>
    </table>
    <div class="px-6 py-4 border-t border-gray-100">
        {{ $organizations->links() }}
    </div>
</div>

@endsection
