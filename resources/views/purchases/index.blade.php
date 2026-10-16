@use('App\Enums\PaymentStatus')
@use('App\Enums\PurchaseStatus')
@use('App\Support\Money')

@php
    $hasFilters = collect($filters)->filter()->isNotEmpty();
@endphp

<x-app-layout>
    <x-slot name="title">{{ __('Purchases') }}</x-slot>

    <x-ui.page-header :title="__('Purchases')" :description="__('Purchase history: goods bought from suppliers.')">
        <x-slot:actions>
            @can('purchases.create')
                <x-ui.button :href="route('purchases.create')" icon="plus">{{ __('New Purchase') }}</x-ui.button>
            @endcan
        </x-slot:actions>
    </x-ui.page-header>

    <x-ui.card :padding="false">
        <form method="GET" class="grid grid-cols-1 gap-3 border-b border-gray-100 p-4 sm:grid-cols-2 lg:grid-cols-6">
            <div class="sm:col-span-2">
                <x-ui.input name="search" type="search" :value="$filters['search']" :placeholder="__('Purchase number or supplier…')" :aria-label="__('Search')" />
            </div>
            <x-ui.select name="status" :options="PurchaseStatus::options()" :selected="$filters['status']?->value" :placeholder="__('All statuses')" :aria-label="__('Status')" />
            <x-ui.select name="payment_status" :options="PaymentStatus::options()" :selected="$filters['payment_status']?->value" :placeholder="__('Any payment')" :aria-label="__('Payment status')" />
            <x-ui.input name="from" type="date" :value="$filters['from']" :aria-label="__('From date')" />
            <x-ui.input name="to" type="date" :value="$filters['to']" :aria-label="__('To date')" />
            <div class="sm:col-span-2 lg:col-span-2">
                <x-ui.select name="supplier" :options="$suppliers->pluck('name', 'id')" :selected="$filters['supplier']" :placeholder="__('All suppliers')" :aria-label="__('Supplier')" />
            </div>
            <div class="flex gap-2 sm:col-span-2 lg:col-span-4 lg:justify-end">
                @if ($hasFilters)
                    <x-ui.button :href="route('purchases.index')" variant="ghost">{{ __('Clear filters') }}</x-ui.button>
                @endif
                <x-ui.button type="submit" variant="secondary" icon="filter">{{ __('Apply') }}</x-ui.button>
            </div>
        </form>

        @if ($purchases->isEmpty())
            <x-ui.empty-state
                icon="purchase-orders"
                :title="$hasFilters ? __('No matching purchases') : __('No purchases yet')"
                :description="$hasFilters ? __('Try a different search or clear the filters.') : __('Record what you buy from suppliers to keep a purchase history.')"
            >
                @if (! $hasFilters)
                    @can('purchases.create')
                        <x-ui.button :href="route('purchases.create')" icon="plus" size="sm">{{ __('New Purchase') }}</x-ui.button>
                    @endcan
                @endif
            </x-ui.empty-state>
        @else
            <x-ui.table>
                <x-slot:head>
                    <th class="px-4 py-3">{{ __('Purchase') }}</th>
                    <th class="px-4 py-3">{{ __('Supplier') }}</th>
                    <th class="hidden px-4 py-3 md:table-cell">{{ __('Date') }}</th>
                    <th class="px-4 py-3 text-right">{{ __('Total') }}</th>
                    <th class="hidden px-4 py-3 sm:table-cell">{{ __('Payment') }}</th>
                    <th class="hidden px-4 py-3 sm:table-cell">{{ __('Status') }}</th>
                    <th class="hidden px-4 py-3 lg:table-cell">{{ __('Created by') }}</th>
                    <th class="px-4 py-3"><span class="sr-only">{{ __('Actions') }}</span></th>
                </x-slot:head>

                @foreach ($purchases as $purchase)
                    <tr class="hover:bg-gray-50">
                        <td class="whitespace-nowrap px-4 py-3">
                            <a href="{{ route('purchases.show', $purchase) }}" class="font-medium text-gray-900 hover:text-primary-600">{{ $purchase->purchase_number }}</a>
                            <p class="text-xs text-gray-500 md:hidden">{{ $purchase->purchase_date->isoFormat('D MMM YYYY') }}</p>
                        </td>
                        <td class="px-4 py-3 text-gray-700">
                            {{ $purchase->supplier->name }}
                            @if ($purchase->supplier->company_name)
                                <p class="text-xs text-gray-400">{{ $purchase->supplier->company_name }}</p>
                            @endif
                        </td>
                        <td class="hidden whitespace-nowrap px-4 py-3 text-gray-600 md:table-cell">{{ $purchase->purchase_date->isoFormat('D MMM YYYY') }}</td>
                        <td class="whitespace-nowrap px-4 py-3 text-right font-medium tabular-nums text-gray-900">{{ Money::format($purchase->total) }}</td>
                        <td class="hidden px-4 py-3 sm:table-cell"><x-ui.badge :color="$purchase->payment_status->color()">{{ $purchase->payment_status->label() }}</x-ui.badge></td>
                        <td class="hidden px-4 py-3 sm:table-cell"><x-ui.badge :color="$purchase->status->color()" dot>{{ $purchase->status->label() }}</x-ui.badge></td>
                        <td class="hidden px-4 py-3 text-gray-600 lg:table-cell">{{ $purchase->creator?->name ?? '—' }}</td>
                        <td class="px-4 py-3">
                            <div class="flex items-center justify-end gap-1">
                                <x-ui.icon-button :href="route('purchases.show', $purchase)" icon="eye" :label="__('View')" />
                                @if ($purchase->isDraft())
                                    @can('purchases.edit')
                                        <x-ui.icon-button :href="route('purchases.edit', $purchase)" icon="pencil" :label="__('Edit')" />
                                    @endcan
                                    @can('purchases.delete')
                                        <x-ui.icon-button
                                            icon="trash"
                                            variant="danger"
                                            :label="__('Delete')"
                                            :confirm="[
                                                'action' => route('purchases.destroy', $purchase),
                                                'title' => __('Delete draft :number?', ['number' => $purchase->purchase_number]),
                                                'message' => __('The draft and its items will be removed. This cannot be undone.'),
                                            ]"
                                        />
                                    @endcan
                                @endif
                            </div>
                        </td>
                    </tr>
                @endforeach
            </x-ui.table>

            <div class="flex flex-col gap-2 border-t border-gray-100 px-4 py-3 text-sm text-gray-500 sm:flex-row sm:items-center sm:justify-between">
                <p>{{ __('Showing :from–:to of :total', ['from' => $purchases->firstItem(), 'to' => $purchases->lastItem(), 'total' => $purchases->total()]) }}</p>
                @if ($purchases->hasPages())
                    {{ $purchases->links() }}
                @endif
            </div>
        @endif
    </x-ui.card>
</x-app-layout>
