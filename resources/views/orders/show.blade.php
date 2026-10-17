@use('App\Enums\PaymentMethod')
@use('App\Enums\SaleReturnStatus')
@use('App\Support\Money')
@use('App\Support\Format')

<x-app-layout>
    <x-slot name="title">{{ $order->invoice_number }}</x-slot>

    <x-ui.page-header
        :title="$order->invoice_number"
        :description="$order->isCancelled() ? __('Cancelled') : __('Completed :date at :time', ['date' => Format::date($order->completed_at), 'time' => Format::time($order->completed_at)])"
    >
        <x-slot:actions>
            <x-ui.button :href="route('orders.index')" variant="ghost" icon="arrow-left">{{ __('Back') }}</x-ui.button>
            @if ($order->isCompleted())
                <x-ui.button :href="route('sales.receipt', $order)" variant="secondary" icon="receipt" target="_blank">{{ __('Receipt') }}</x-ui.button>
                @can('sales.edit')
                    <form method="POST" action="{{ route('sales.cancel', $order) }}"
                          onsubmit="return confirm('{{ __('Cancel sale :invoice? This cannot be undone.', ['invoice' => $order->invoice_number]) }}')">
                        @csrf
                        <x-ui.button type="submit" variant="danger" icon="x-circle">{{ __('Cancel Sale') }}</x-ui.button>
                    </form>
                @endcan
            @endif
            @if ($order->isCancelled())
                <x-ui.badge color="danger" dot>{{ __('Cancelled') }}</x-ui.badge>
            @endif
        </x-slot:actions>
    </x-ui.page-header>

    <div class="grid grid-cols-1 gap-6 lg:grid-cols-3">

        {{-- Left: items + totals --}}
        <div class="space-y-6 lg:col-span-2">

            <x-ui.card :title="__('Items')">
                <table class="w-full text-sm">
                    <thead>
                        <tr class="border-b border-gray-100 text-left text-xs font-medium uppercase tracking-wide text-gray-500">
                            <th class="pb-2 pr-4">{{ __('Item') }}</th>
                            <th class="pb-2 pr-4 text-right">{{ __('Qty') }}</th>
                            <th class="pb-2 pr-4 text-right">{{ __('Unit price') }}</th>
                            <th class="pb-2 text-right">{{ __('Amount') }}</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-50">
                        @foreach ($order->items as $item)
                            <tr>
                                <td class="py-2 pr-4">
                                    <p class="font-medium text-gray-900">{{ $item->displayName() }}</p>
                                    @foreach ($item->modifiers as $mod)
                                        <p class="text-xs text-gray-500">+ {{ $mod->name }}@if ((float) $mod->price > 0) ({{ Money::number($mod->price) }})@endif</p>
                                    @endforeach
                                    @if ($item->note)
                                        <p class="text-xs text-gray-400">» {{ $item->note }}</p>
                                    @endif
                                </td>
                                <td class="py-2 pr-4 text-right tabular-nums text-gray-700">{{ $item->quantity }}</td>
                                <td class="py-2 pr-4 text-right tabular-nums text-gray-700">{{ Money::number($item->unit_price) }}</td>
                                <td class="py-2 text-right tabular-nums font-medium text-gray-900">{{ Money::number($item->total) }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </x-ui.card>

            <x-ui.card :title="__('Totals')">
                <dl class="space-y-1.5 text-sm">
                    <div class="flex justify-between">
                        <dt class="text-gray-500">{{ __('Subtotal') }}</dt>
                        <dd class="tabular-nums text-gray-900">{{ Money::number($order->subtotal) }}</dd>
                    </div>
                    @if ((float) $order->discount > 0)
                        <div class="flex justify-between">
                            <dt class="text-gray-500">{{ __('Discount') }}</dt>
                            <dd class="tabular-nums text-gray-900">− {{ Money::number($order->discount) }}</dd>
                        </div>
                    @endif
                    @if ((float) $order->tax > 0)
                        <div class="flex justify-between">
                            <dt class="text-gray-500">{{ settings('tax.label') ?: __('Tax') }}</dt>
                            <dd class="tabular-nums text-gray-900">{{ Money::number($order->tax) }}</dd>
                        </div>
                    @endif
                    @if ((float) $order->delivery_fee > 0)
                        <div class="flex justify-between">
                            <dt class="text-gray-500">{{ __('Delivery fee') }}</dt>
                            <dd class="tabular-nums text-gray-900">{{ Money::number($order->delivery_fee) }}</dd>
                        </div>
                    @endif
                    <div class="flex justify-between border-t border-gray-200 pt-2 text-base font-bold">
                        <dt class="text-gray-900">{{ __('Total') }}</dt>
                        <dd class="tabular-nums text-gray-900">{{ Money::format($order->total) }}</dd>
                    </div>
                </dl>

                <div class="mt-4 border-t border-gray-100 pt-4 text-sm">
                    <p class="font-medium text-gray-700">{{ __('Payment') }}: <span class="font-bold">{{ $order->payment_method?->label() }}</span></p>
                    @if ($order->payment_method === PaymentMethod::Mixed)
                        <ul class="mt-1 space-y-0.5 text-gray-600">
                            @foreach ($order->payments as $payment)
                                <li class="flex justify-between">
                                    <span>{{ $payment->payment_method->label() }}</span>
                                    <span class="tabular-nums">{{ Money::number($payment->amount) }}</span>
                                </li>
                            @endforeach
                        </ul>
                    @elseif ($order->payment_method === PaymentMethod::Cash)
                        <p class="mt-1 text-gray-600">{{ __('Paid') }}: {{ Money::number($order->cash_received) }} · {{ __('Change') }}: {{ Money::number($order->change_amount) }}</p>
                    @endif
                </div>
            </x-ui.card>

            @if ($order->returns->isNotEmpty())
                <x-ui.card :title="__('Returns')">
                    <table class="w-full text-sm">
                        <thead>
                            <tr class="border-b border-gray-100 text-left text-xs font-medium uppercase tracking-wide text-gray-500">
                                <th class="pb-2 pr-4">{{ __('Return No.') }}</th>
                                <th class="pb-2 pr-4">{{ __('Date') }}</th>
                                <th class="pb-2 pr-4 text-right">{{ __('Refund') }}</th>
                                <th class="pb-2">{{ __('Status') }}</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-50">
                            @foreach ($order->returns as $ret)
                                <tr>
                                    <td class="py-2 pr-4"><a href="{{ route('returns.show', $ret) }}" class="font-medium text-primary-600 hover:underline">{{ $ret->return_number }}</a></td>
                                    <td class="py-2 pr-4 text-gray-600">{{ Format::date($ret->return_date) }}</td>
                                    <td class="py-2 pr-4 text-right tabular-nums font-medium text-gray-900">{{ Money::format($ret->refund_amount) }}</td>
                                    <td class="py-2"><x-ui.badge :color="$ret->status->color()" dot>{{ $ret->status->label() }}</x-ui.badge></td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </x-ui.card>
            @endif

        </div>

        {{-- Right: order details sidebar --}}
        <div class="space-y-6">

            <x-ui.card :title="__('Order Details')">
                <dl class="space-y-3 text-sm">
                    <div>
                        <dt class="text-xs font-medium uppercase tracking-wide text-gray-400">{{ __('Invoice') }}</dt>
                        <dd class="mt-0.5 font-semibold text-gray-900">{{ $order->invoice_number }}</dd>
                    </div>
                    <div>
                        <dt class="text-xs font-medium uppercase tracking-wide text-gray-400">{{ __('Date & Time') }}</dt>
                        <dd class="mt-0.5 text-gray-700">{{ Format::date($order->completed_at) }} {{ Format::time($order->completed_at) }}</dd>
                    </div>
                    <div>
                        <dt class="text-xs font-medium uppercase tracking-wide text-gray-400">{{ __('Order Type') }}</dt>
                        <dd class="mt-0.5 text-gray-700">
                            {{ $order->order_type?->label() }}
                            @if ($order->table)
                                · {{ $order->table->name }}
                            @endif
                        </dd>
                    </div>
                    <div>
                        <dt class="text-xs font-medium uppercase tracking-wide text-gray-400">{{ __('Cashier') }}</dt>
                        <dd class="mt-0.5 text-gray-700">{{ $order->user?->name ?? '—' }}</dd>
                    </div>
                    @if ($order->return_status !== null && $order->return_status !== \App\Enums\SaleReturnStatus::None)
                        <div>
                            <dt class="text-xs font-medium uppercase tracking-wide text-gray-400">{{ __('Return Status') }}</dt>
                            <dd class="mt-0.5"><x-ui.badge :color="$order->return_status->color()" dot>{{ $order->return_status->label() }}</x-ui.badge></dd>
                        </div>
                    @endif
                    @if ($order->order_note)
                        <div>
                            <dt class="text-xs font-medium uppercase tracking-wide text-gray-400">{{ __('Order Note') }}</dt>
                            <dd class="mt-0.5 whitespace-pre-line text-gray-700">{{ $order->order_note }}</dd>
                        </div>
                    @endif
                </dl>
            </x-ui.card>

            <x-ui.card :title="__('Customer')">
                <dl class="space-y-2 text-sm">
                    <div>
                        <dt class="text-xs font-medium uppercase tracking-wide text-gray-400">{{ __('Name') }}</dt>
                        <dd class="mt-0.5 text-gray-700">
                            @if ($order->customer)
                                <a href="{{ route('customers.show', $order->customer) }}" class="text-primary-600 hover:underline">{{ $order->customerLabel() }}</a>
                            @else
                                {{ $order->customerLabel() }}
                            @endif
                        </dd>
                    </div>
                    @if ($order->customer_phone)
                        <div>
                            <dt class="text-xs font-medium uppercase tracking-wide text-gray-400">{{ __('Phone') }}</dt>
                            <dd class="mt-0.5 text-gray-700">{{ $order->customer_phone }}</dd>
                        </div>
                    @endif
                    @if ($order->customer_address)
                        <div>
                            <dt class="text-xs font-medium uppercase tracking-wide text-gray-400">{{ __('Address') }}</dt>
                            <dd class="mt-0.5 whitespace-pre-line text-gray-700">{{ $order->customer_address }}</dd>
                        </div>
                    @endif
                </dl>
            </x-ui.card>

            @if ($order->kitchenOrders->isNotEmpty())
                <x-ui.card :title="__('Kitchen Orders')">
                    <ul class="space-y-2 text-sm">
                        @foreach ($order->kitchenOrders as $kot)
                            <li class="flex items-center justify-between gap-2">
                                <a href="{{ route('kitchen.orders.show', $kot) }}" class="font-medium text-primary-600 hover:underline">{{ $kot->kot_number }}</a>
                                <x-ui.badge :color="$kot->status->color()" dot>{{ $kot->status->label() }}</x-ui.badge>
                            </li>
                        @endforeach
                    </ul>
                </x-ui.card>
            @endif

        </div>
    </div>
</x-app-layout>
