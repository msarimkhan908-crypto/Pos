@use('App\Enums\RecordStatus')

<x-app-layout>
    <x-slot name="title">{{ __('Customers') }}</x-slot>

    <x-ui.page-header :title="__('Customers')" :description="__('People you sell to.')">
        <x-slot:actions>
            @can('customers.create')
                <x-ui.button :href="route('customers.create')" icon="plus">{{ __('New Customer') }}</x-ui.button>
            @endcan
        </x-slot:actions>
    </x-ui.page-header>

    <x-ui.card :padding="false">
        <form method="GET" class="flex flex-col gap-3 border-b border-gray-100 p-4 sm:flex-row sm:items-center">
            <div class="flex-1">
                <x-ui.input name="search" type="search" :value="$search" :placeholder="__('Name, phone or email…')" :aria-label="__('Search')" />
            </div>
            <div class="sm:w-44">
                <x-ui.select name="status" :options="RecordStatus::options()" :selected="$status?->value" :placeholder="__('All statuses')" :aria-label="__('Status')" />
            </div>
            <div class="flex gap-2">
                <x-ui.button type="submit" variant="secondary" icon="filter">{{ __('Filter') }}</x-ui.button>
                @if ($search || $status)
                    <x-ui.button :href="route('customers.index')" variant="ghost">{{ __('Clear') }}</x-ui.button>
                @endif
            </div>
        </form>

        @if ($customers->isEmpty())
            <x-ui.empty-state
                icon="customers"
                :title="$search || $status ? __('No matching customers') : __('No customers yet')"
                :description="$search || $status ? __('Try a different search or filter.') : __('Add customers to keep their contact details for future sales.')"
            />
        @else
            <x-ui.table>
                <x-slot:head>
                    <th class="px-4 py-3">{{ __('Customer') }}</th>
                    <th class="hidden px-4 py-3 sm:table-cell">{{ __('Phone') }}</th>
                    <th class="hidden px-4 py-3 lg:table-cell">{{ __('Email') }}</th>
                    <th class="px-4 py-3">{{ __('Status') }}</th>
                    <th class="hidden px-4 py-3 md:table-cell">{{ __('Created') }}</th>
                    <th class="px-4 py-3"><span class="sr-only">{{ __('Actions') }}</span></th>
                </x-slot:head>

                @foreach ($customers as $customer)
                    <tr class="hover:bg-gray-50">
                        <td class="px-4 py-3">
                            <a href="{{ route('customers.show', $customer) }}" class="font-medium text-gray-900 hover:text-primary-600">{{ $customer->name }}</a>
                            <p class="text-xs text-gray-500 sm:hidden">{{ $customer->phone }}</p>
                        </td>
                        <td class="hidden whitespace-nowrap px-4 py-3 text-gray-600 sm:table-cell">{{ $customer->phone ?? '—' }}</td>
                        <td class="hidden px-4 py-3 text-gray-600 lg:table-cell">{{ $customer->email ?? '—' }}</td>
                        <td class="px-4 py-3"><x-ui.status-badge :active="$customer->isActive()" /></td>
                        <td class="hidden whitespace-nowrap px-4 py-3 text-gray-500 md:table-cell">{{ $customer->created_at->isoFormat('D MMM YYYY') }}</td>
                        <td class="px-4 py-3">
                            <div class="flex items-center justify-end gap-1">
                                <x-ui.icon-button :href="route('customers.show', $customer)" icon="eye" :label="__('View')" />
                                @can('customers.edit')
                                    <x-ui.icon-button :href="route('customers.edit', $customer)" icon="pencil" :label="__('Edit')" />
                                    <form method="POST" action="{{ route('customers.toggle', $customer) }}">
                                        @csrf
                                        @method('PATCH')
                                        <x-ui.icon-button type="submit" icon="power" :label="$customer->isActive() ? __('Deactivate') : __('Activate')" />
                                    </form>
                                @endcan
                                @can('customers.delete')
                                    <x-ui.icon-button
                                        icon="trash"
                                        variant="danger"
                                        :label="__('Delete')"
                                        :confirm="[
                                            'action' => route('customers.destroy', $customer),
                                            'title' => __('Delete :name?', ['name' => $customer->name]),
                                            'message' => __('This cannot be undone.'),
                                        ]"
                                    />
                                @endcan
                            </div>
                        </td>
                    </tr>
                @endforeach
            </x-ui.table>

            <div class="flex flex-col gap-2 border-t border-gray-100 px-4 py-3 text-sm text-gray-500 sm:flex-row sm:items-center sm:justify-between">
                <p>{{ __('Showing :from–:to of :total', ['from' => $customers->firstItem(), 'to' => $customers->lastItem(), 'total' => $customers->total()]) }}</p>
                @if ($customers->hasPages())
                    {{ $customers->links() }}
                @endif
            </div>
        @endif
    </x-ui.card>
</x-app-layout>
