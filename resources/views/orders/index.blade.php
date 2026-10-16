@use('App\Enums\PaymentMethod')
@use('App\Support\Money')
@use('App\Support\Format')

<x-app-layout>
    <x-slot name="title">{{ __('Orders') }}</x-slot>

    <x-ui.page-header :title="__('Orders')" :description="__('All completed sales.')" />

    <x-ui.card :padding="false">
        <form method="GET" class="grid gap-3 border-b border-gray-100 p-4 sm:grid-cols-2 lg:grid-cols-[minmax(0,1fr)_10rem_10rem_10rem_auto] lg:items-end">
            <x-ui.input name="search" type="search" :label="__('Search')" :value="$search" :placeholder="__('Invoice number or customer')" />
            <x-ui.input name="from" type="date" :label="__('From')" :value="$from" />
            <x-ui.input name="to" type="date" :label="__('To')" :value="$to" />
            <x-ui.select name="payment" :label="__('Payment')" :options="$paymentOptions" :selected="$payment" :placeholder="__('All methods')" />
            <div class="flex gap-2">
                <x-ui.button type="submit" variant="secondary" icon="filter">{{ __('Filter') }}</x-ui.button>
                @if ($search || $from || $to || $payment)
                    <x-ui.button :href="route('orders.index')" variant="ghost">{{ __('Clear') }}</x-ui.button>
                @endif
            </div>
        </form>

        @if ($orders->isEmpty())
            <x-ui.empty-state
                icon="orders"
                :title="$search || $from || $to || $payment ? __('No matching orders') : __('No orders yet')"
                :description="$search || $from || $to || $payment ? __('Try a different search or filter.') : __('Completed sales will appear here.')"
            />
        @else
            <x-ui.table>
                <x-slot:head>
                    <th class="px-4 py-3">{{ __('Invoice') }}</th>
                    <th class="hidden px-4 py-3 sm:table-cell">{{ __('Date') }}</th>
                    <th class="hidden px-4 py-3 md:table-cell">{{ __('Customer') }}</th>
                    <th class="hidden px-4 py-3 lg:table-cell">{{ __('Type') }}</th>
                    <th class="hidden px-4 py-3 sm:table-cell">{{ __('Items') }}</th>
                    <th class="px-4 py-3 text-right">{{ __('Total') }}</th>
                    <th class="hidden px-4 py-3 md:table-cell">{{ __('Payment') }}</th>
                    <th class="hidden px-4 py-3 xl:table-cell">{{ __('Cashier') }}</th>
                    <th class="px-4 py-3"><span class="sr-only">{{ __('Actions') }}</span></th>
                </x-slot:head>

                @foreach ($orders as $order)
                    <tr class="hover:bg-gray-50">
                        <td class="px-4 py-3">
                            <a href="{{ route('orders.show', $order) }}" class="font-medium text-gray-900 hover:text-primary-600">
                                {{ $order->invoice_number }}
                            </a>
                            <p class="text-xs text-gray-500 sm:hidden">{{ Format::date($order->completed_at) }}</p>
                        </td>
                        <td class="hidden whitespace-nowrap px-4 py-3 text-gray-600 sm:table-cell">
                            {{ Format::date($order->completed_at) }}
                            <span class="block text-xs text-gray-400">{{ Format::time($order->completed_at) }}</span>
                        </td>
                        <td class="hidden px-4 py-3 text-gray-700 md:table-cell">{{ $order->customerLabel() }}</td>
                        <td class="hidden px-4 py-3 text-gray-600 lg:table-cell">
                            {{ $order->order_type?->label() }}
                            @if ($order->table)
                                <span class="block text-xs text-gray-400">{{ $order->table->name }}</span>
                            @endif
                        </td>
                        <td class="hidden whitespace-nowrap px-4 py-3 text-gray-600 sm:table-cell">{{ $order->items_count ?? $order->items->count() }}</td>
                        <td class="whitespace-nowrap px-4 py-3 text-right font-medium tabular-nums text-gray-900">{{ Money::format($order->total) }}</td>
                        <td class="hidden px-4 py-3 text-gray-600 md:table-cell">{{ $order->payment_method?->label() }}</td>
                        <td class="hidden px-4 py-3 text-gray-600 xl:table-cell">{{ $order->user?->name ?? '—' }}</td>
                        <td class="px-4 py-3 text-right">
                            <x-ui.icon-button :href="route('orders.show', $order)" icon="eye" :label="__('View')" />
                        </td>
                    </tr>
                @endforeach
            </x-ui.table>

            <div class="flex flex-col gap-2 border-t border-gray-100 px-4 py-3 text-sm text-gray-500 sm:flex-row sm:items-center sm:justify-between">
                <p>{{ __('Showing :from–:to of :total', ['from' => $orders->firstItem(), 'to' => $orders->lastItem(), 'total' => $orders->total()]) }}</p>
                @if ($orders->hasPages())
                    {{ $orders->links() }}
                @endif
            </div>
        @endif
    </x-ui.card>
</x-app-layout>
