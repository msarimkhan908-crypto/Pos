@use('App\Enums\RecordStatus')

<x-app-layout>
    <x-slot name="title">{{ __('Suppliers') }}</x-slot>

    <x-ui.page-header :title="__('Suppliers')" :description="__('Companies and people you buy stock from.')">
        <x-slot:actions>
            @can('suppliers.create')
                <x-ui.button :href="route('suppliers.create')" icon="plus">{{ __('New Supplier') }}</x-ui.button>
            @endcan
        </x-slot:actions>
    </x-ui.page-header>

    <x-ui.card :padding="false">
        <form method="GET" class="flex flex-col gap-3 border-b border-gray-100 p-4 sm:flex-row sm:items-center">
            <div class="flex-1">
                <x-ui.input name="search" type="search" :value="$search" :placeholder="__('Name, company, phone or email…')" :aria-label="__('Search')" />
            </div>
            <div class="sm:w-44">
                <x-ui.select name="status" :options="RecordStatus::options()" :selected="$status?->value" :placeholder="__('All statuses')" :aria-label="__('Status')" />
            </div>
            <div class="flex gap-2">
                <x-ui.button type="submit" variant="secondary" icon="filter">{{ __('Filter') }}</x-ui.button>
                @if ($search || $status)
                    <x-ui.button :href="route('suppliers.index')" variant="ghost">{{ __('Clear') }}</x-ui.button>
                @endif
            </div>
        </form>

        @if ($suppliers->isEmpty())
            <x-ui.empty-state
                icon="suppliers"
                :title="$search || $status ? __('No matching suppliers') : __('No suppliers yet')"
                :description="$search || $status ? __('Try a different search or filter.') : __('Add the companies you buy from to start recording purchases.')"
            />
        @else
            <x-ui.table>
                <x-slot:head>
                    <th class="px-4 py-3">{{ __('Supplier') }}</th>
                    <th class="hidden px-4 py-3 md:table-cell">{{ __('Phone') }}</th>
                    <th class="hidden px-4 py-3 lg:table-cell">{{ __('Email') }}</th>
                    <th class="px-4 py-3">{{ __('Status') }}</th>
                    <th class="hidden px-4 py-3 sm:table-cell">{{ __('Created') }}</th>
                    <th class="px-4 py-3"><span class="sr-only">{{ __('Actions') }}</span></th>
                </x-slot:head>

                @foreach ($suppliers as $supplier)
                    <tr class="hover:bg-gray-50">
                        <td class="px-4 py-3">
                            <a href="{{ route('suppliers.show', $supplier) }}" class="font-medium text-gray-900 hover:text-primary-600">{{ $supplier->name }}</a>
                            <p class="text-xs text-gray-500">
                                {{ $supplier->company_name ?? '—' }}
                                · {{ trans_choice(':count purchase|:count purchases', $supplier->purchases_count) }}
                            </p>
                        </td>
                        <td class="hidden whitespace-nowrap px-4 py-3 text-gray-600 md:table-cell">{{ $supplier->phone ?? '—' }}</td>
                        <td class="hidden px-4 py-3 text-gray-600 lg:table-cell">{{ $supplier->email ?? '—' }}</td>
                        <td class="px-4 py-3"><x-ui.status-badge :active="$supplier->isActive()" /></td>
                        <td class="hidden whitespace-nowrap px-4 py-3 text-gray-500 sm:table-cell">{{ $supplier->created_at->isoFormat('D MMM YYYY') }}</td>
                        <td class="px-4 py-3">
                            <div class="flex items-center justify-end gap-1">
                                <x-ui.icon-button :href="route('suppliers.show', $supplier)" icon="eye" :label="__('View')" />
                                @can('suppliers.edit')
                                    <x-ui.icon-button :href="route('suppliers.edit', $supplier)" icon="pencil" :label="__('Edit')" />
                                    <form method="POST" action="{{ route('suppliers.toggle', $supplier) }}">
                                        @csrf
                                        @method('PATCH')
                                        <x-ui.icon-button type="submit" icon="power" :label="$supplier->isActive() ? __('Deactivate') : __('Activate')" />
                                    </form>
                                @endcan
                                @can('suppliers.delete')
                                    <x-ui.icon-button
                                        icon="trash"
                                        variant="danger"
                                        :label="__('Delete')"
                                        :confirm="[
                                            'action' => route('suppliers.destroy', $supplier),
                                            'title' => __('Delete :name?', ['name' => $supplier->name]),
                                            'message' => __('Suppliers with purchases cannot be deleted. This cannot be undone.'),
                                        ]"
                                    />
                                @endcan
                            </div>
                        </td>
                    </tr>
                @endforeach
            </x-ui.table>

            <div class="flex flex-col gap-2 border-t border-gray-100 px-4 py-3 text-sm text-gray-500 sm:flex-row sm:items-center sm:justify-between">
                <p>{{ __('Showing :from–:to of :total', ['from' => $suppliers->firstItem(), 'to' => $suppliers->lastItem(), 'total' => $suppliers->total()]) }}</p>
                @if ($suppliers->hasPages())
                    {{ $suppliers->links() }}
                @endif
            </div>
        @endif
    </x-ui.card>
</x-app-layout>
