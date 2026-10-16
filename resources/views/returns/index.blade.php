@use('App\Enums\ReturnStatus')
@use('App\Support\Money')
@use('App\Support\Format')

<x-app-layout>
    <x-slot name="title">{{ __('Returns') }}</x-slot>

    <x-ui.page-header :title="__('Returns')" :description="__('Refunds for items returned from completed sales.')">
        <x-slot:actions>
            @can('returns.create')
                <x-ui.button :href="route('returns.create')" icon="plus">{{ __('New Return') }}</x-ui.button>
            @endcan
        </x-slot:actions>
    </x-ui.page-header>

    <x-ui.card :padding="false">
        <form method="GET" class="grid gap-3 border-b border-gray-100 p-4 sm:grid-cols-2 lg:grid-cols-[minmax(0,1fr)_10rem_10rem_10rem_auto] lg:items-end">
            <x-ui.input name="search" type="search" :label="__('Search')" :value="$filters['search'] ?? ''" :placeholder="__('Return no., invoice or customer')" />
            <x-ui.select name="status" :label="__('Status')" :options="ReturnStatus::options()" :selected="$filters['status'] ?? null" :placeholder="__('All statuses')" />
            <x-ui.input name="from" type="date" :label="__('From')" :value="$filters['from'] ?? ''" />
            <x-ui.input name="to" type="date" :label="__('To')" :value="$filters['to'] ?? ''" />
            <div class="flex gap-2">
                <x-ui.button type="submit" variant="secondary" icon="filter">{{ __('Filter') }}</x-ui.button>
                @if (array_filter($filters))
                    <x-ui.button :href="route('returns.index')" variant="ghost">{{ __('Clear') }}</x-ui.button>
                @endif
            </div>
        </form>

        @if ($returns->isEmpty())
            <x-ui.empty-state icon="history"
                :title="array_filter($filters) ? __('No matching returns') : __('No returns yet')"
                :description="array_filter($filters) ? __('Try a different search or filter.') : __('Returns you process against completed sales will be listed here.')" />
        @else
            <x-ui.table>
                <x-slot:head>
                    <th class="px-4 py-3">{{ __('Return') }}</th>
                    <th class="px-4 py-3">{{ __('Sale') }}</th>
                    <th class="hidden px-4 py-3 md:table-cell">{{ __('Customer') }}</th>
                    <th class="px-4 py-3">{{ __('Date') }}</th>
                    <th class="px-4 py-3 text-right">{{ __('Refund') }}</th>
                    <th class="hidden px-4 py-3 lg:table-cell">{{ __('Method') }}</th>
                    <th class="px-4 py-3">{{ __('Status') }}</th>
                    <th class="hidden px-4 py-3 xl:table-cell">{{ __('Processed by') }}</th>
                    <th class="px-4 py-3"><span class="sr-only">{{ __('Actions') }}</span></th>
                </x-slot:head>
                @foreach ($returns as $return)
                    <tr class="hover:bg-gray-50">
                        <td class="px-4 py-3 font-medium text-gray-900"><a href="{{ route('returns.show', $return) }}" class="hover:text-primary-600">{{ $return->return_number }}</a></td>
                        <td class="px-4 py-3 text-gray-700">{{ $return->sale?->invoice_number }}</td>
                        <td class="hidden px-4 py-3 text-gray-700 md:table-cell">{{ $return->customerLabel() }}</td>
                        <td class="whitespace-nowrap px-4 py-3 text-gray-600">{{ Format::date($return->return_date) }}</td>
                        <td class="whitespace-nowrap px-4 py-3 text-right font-medium tabular-nums text-gray-900">{{ Money::format($return->refund_amount) }}</td>
                        <td class="hidden px-4 py-3 text-gray-600 lg:table-cell">{{ $return->refund_method->label() }}</td>
                        <td class="px-4 py-3"><x-ui.badge :color="$return->status->color()" dot>{{ $return->status->label() }}</x-ui.badge></td>
                        <td class="hidden px-4 py-3 text-gray-600 xl:table-cell">{{ $return->processor?->name ?? '—' }}</td>
                        <td class="px-4 py-3 text-right"><x-ui.icon-button :href="route('returns.show', $return)" icon="eye" :label="__('View')" /></td>
                    </tr>
                @endforeach
            </x-ui.table>
            @if ($returns->hasPages())
                <div class="border-t border-gray-100 px-4 py-3">{{ $returns->links() }}</div>
            @endif
        @endif
    </x-ui.card>
</x-app-layout>
