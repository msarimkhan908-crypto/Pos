@use('App\Support\Money')
@use('App\Support\Format')
@use('App\Enums\PaymentMethod')
@use('App\Enums\SaleReturnStatus')

<x-app-layout>
    <x-slot name="title">{{ __('Sales Report') }}</x-slot>

    <x-ui.page-header :title="__('Sales Report')" :description="__('Completed sales in the period.')" />

    @include('reports._toolbar', ['current' => 'reports.sales', 'selects' => [
        ['name' => 'customer', 'label' => __('Customer'), 'options' => $customers->all(), 'selected' => $filters['customer'] ?? null, 'placeholder' => __('All customers')],
        ['name' => 'cashier', 'label' => __('Cashier'), 'options' => $cashiers->all(), 'selected' => $filters['cashier'] ?? null, 'placeholder' => __('All cashiers')],
        ['name' => 'method', 'label' => __('Payment'), 'options' => collect(PaymentMethod::cases())->mapWithKeys(fn ($m) => [$m->value => $m->label()])->all(), 'selected' => $filters['method'] ?? null, 'placeholder' => __('All methods')],
        ['name' => 'returns', 'label' => __('Status'), 'options' => SaleReturnStatus::options(), 'selected' => $filters['returns'] ?? null, 'placeholder' => __('All sales')],
    ]])

    <x-ui.card :padding="false">
        @if ($sales->isEmpty())
            <x-ui.empty-state icon="sales-reports" :title="__('No sales in this period')" :description="__('Try another period or clear the filters.')" />
        @else
            <x-ui.table>
                <x-slot:head>
                    <th class="px-4 py-3">{{ __('Invoice') }}</th>
                    <th class="px-4 py-3">{{ __('Date') }}</th>
                    <th class="hidden px-4 py-3 md:table-cell">{{ __('Customer') }}</th>
                    <th class="hidden px-4 py-3 lg:table-cell">{{ __('Cashier') }}</th>
                    <th class="hidden px-4 py-3 text-right xl:table-cell">{{ __('Subtotal') }}</th>
                    <th class="hidden px-4 py-3 text-right xl:table-cell">{{ __('Discount') }}</th>
                    <th class="hidden px-4 py-3 text-right lg:table-cell">{{ settings('tax.label') }}</th>
                    <th class="px-4 py-3 text-right">{{ __('Total') }}</th>
                    <th class="hidden px-4 py-3 md:table-cell">{{ __('Payment') }}</th>
                    <th class="px-4 py-3">{{ __('Status') }}</th>
                    <th class="no-print px-4 py-3"><span class="sr-only">{{ __('Actions') }}</span></th>
                </x-slot:head>
                @foreach ($sales as $sale)
                    <tr class="hover:bg-gray-50">
                        <td class="px-4 py-3 font-medium text-gray-900"><a href="{{ route('sales.receipt', $sale) }}" class="hover:text-primary-600">{{ $sale->invoice_number }}</a></td>
                        <td class="whitespace-nowrap px-4 py-3 text-gray-600">{{ Format::dateTime($sale->completed_at) }}</td>
                        <td class="hidden px-4 py-3 text-gray-700 md:table-cell">{{ $sale->customerLabel() }}</td>
                        <td class="hidden px-4 py-3 text-gray-600 lg:table-cell">{{ $sale->user?->name ?? '—' }}</td>
                        <td class="hidden px-4 py-3 text-right tabular-nums xl:table-cell">{{ Money::number($sale->subtotal) }}</td>
                        <td class="hidden px-4 py-3 text-right tabular-nums xl:table-cell">{{ Money::number($sale->discount) }}</td>
                        <td class="hidden px-4 py-3 text-right tabular-nums lg:table-cell">{{ Money::number($sale->tax) }}</td>
                        <td class="whitespace-nowrap px-4 py-3 text-right font-medium tabular-nums text-gray-900">{{ Money::format($sale->total) }}</td>
                        <td class="hidden px-4 py-3 text-gray-600 md:table-cell">{{ $sale->payment_method?->label() }}</td>
                        <td class="px-4 py-3">
                            @if ($sale->return_status === SaleReturnStatus::None)
                                <x-ui.badge color="success" dot>{{ __('Completed') }}</x-ui.badge>
                            @else
                                <x-ui.badge :color="$sale->return_status->color()" dot>{{ $sale->return_status->label() }}</x-ui.badge>
                            @endif
                        </td>
                        <td class="no-print px-4 py-3 text-right">
                            <div class="flex items-center justify-end gap-1">
                                @can('sales.view')
                                    <x-ui.button :href="route('sales.edit', $sale)" variant="ghost" size="sm">{{ __('Edit') }}</x-ui.button>
                                @endcan
                                @can('returns.create')
                                    @unless ($sale->return_status === SaleReturnStatus::Full)
                                        <x-ui.button :href="route('returns.create', ['sale' => $sale->id])" variant="ghost" size="sm">{{ __('Return') }}</x-ui.button>
                                    @endunless
                                @endcan
                            </div>
                        </td>
                    </tr>
                @endforeach
                <tr class="bg-gray-50 font-semibold" data-totals>
                    <td class="px-4 py-3" colspan="2">{{ trans_choice(':count sale|:count sales', $totals->count) }}</td>
                    <td class="hidden md:table-cell"></td>
                    <td class="hidden lg:table-cell"></td>
                    <td class="hidden px-4 py-3 text-right tabular-nums xl:table-cell">{{ Money::number($totals->subtotal) }}</td>
                    <td class="hidden px-4 py-3 text-right tabular-nums xl:table-cell">{{ Money::number($totals->discount) }}</td>
                    <td class="hidden px-4 py-3 text-right tabular-nums lg:table-cell">{{ Money::number($totals->tax) }}</td>
                    <td class="px-4 py-3 text-right tabular-nums">{{ Money::format($totals->total) }}</td>
                    <td class="hidden px-4 py-3 text-gray-600 md:table-cell" colspan="1">@if ((float) $totals->refunded > 0){{ __('Refunded') }} {{ Money::format($totals->refunded) }}@endif</td>
                    <td colspan="2"></td>
                </tr>
            </x-ui.table>
            @if ($sales->hasPages())
                <div class="no-print border-t border-gray-100 px-4 py-3">{{ $sales->links() }}</div>
            @endif
        @endif
    </x-ui.card>
</x-app-layout>
