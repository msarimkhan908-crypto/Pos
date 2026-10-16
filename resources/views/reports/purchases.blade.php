@use('App\Support\Money')
@use('App\Support\Format')
@use('App\Enums\PurchaseStatus')

<x-app-layout>
    <x-slot name="title">{{ __('Purchase Report') }}</x-slot>

    <x-ui.page-header :title="__('Purchase Report')" :description="__('Purchases by purchase date. Completed purchases by default.')" />

    @include('reports._toolbar', ['current' => 'reports.purchases', 'selects' => [
        ['name' => 'supplier', 'label' => __('Supplier'), 'options' => $suppliers->all(), 'selected' => $filters['supplier'] ?? null, 'placeholder' => __('All suppliers')],
        ['name' => 'status', 'label' => __('Status'), 'options' => ['all' => __('All statuses')] + collect(PurchaseStatus::cases())->mapWithKeys(fn ($s) => [$s->value => $s->label()])->all(), 'selected' => $filters['status'], 'placeholder' => __('Completed')],
    ]])

    <x-ui.card :padding="false">
        @if ($purchases->isEmpty())
            <x-ui.empty-state icon="purchase-orders" :title="__('No purchases in this period')" :description="__('Try another period or clear the filters.')" />
        @else
            <x-ui.table>
                <x-slot:head>
                    <th class="px-4 py-3">{{ __('Purchase') }}</th>
                    <th class="px-4 py-3">{{ __('Date') }}</th>
                    <th class="px-4 py-3">{{ __('Supplier') }}</th>
                    <th class="hidden px-4 py-3 text-right lg:table-cell">{{ __('Subtotal') }}</th>
                    <th class="hidden px-4 py-3 text-right lg:table-cell">{{ __('Discount') }}</th>
                    <th class="hidden px-4 py-3 text-right md:table-cell">{{ __('Tax') }}</th>
                    <th class="px-4 py-3 text-right">{{ __('Total') }}</th>
                    <th class="px-4 py-3">{{ __('Status') }}</th>
                </x-slot:head>
                @foreach ($purchases as $purchase)
                    <tr class="hover:bg-gray-50">
                        <td class="px-4 py-3 font-medium text-gray-900">
                            @can('purchases.view')<a href="{{ route('purchases.show', $purchase) }}" class="hover:text-primary-600">{{ $purchase->purchase_number }}</a>@else{{ $purchase->purchase_number }}@endcan
                        </td>
                        <td class="whitespace-nowrap px-4 py-3 text-gray-600">{{ Format::date($purchase->purchase_date) }}</td>
                        <td class="px-4 py-3 text-gray-700">{{ $purchase->supplier?->name }}</td>
                        <td class="hidden px-4 py-3 text-right tabular-nums lg:table-cell">{{ Money::number($purchase->subtotal) }}</td>
                        <td class="hidden px-4 py-3 text-right tabular-nums lg:table-cell">{{ Money::number($purchase->discount) }}</td>
                        <td class="hidden px-4 py-3 text-right tabular-nums md:table-cell">{{ Money::number($purchase->tax) }}</td>
                        <td class="whitespace-nowrap px-4 py-3 text-right font-medium tabular-nums">{{ Money::format($purchase->total) }}</td>
                        <td class="px-4 py-3"><x-ui.badge :color="$purchase->status->color()" dot>{{ $purchase->status->label() }}</x-ui.badge></td>
                    </tr>
                @endforeach
                <tr class="bg-gray-50 font-semibold" data-totals>
                    <td class="px-4 py-3" colspan="3">{{ trans_choice(':count purchase|:count purchases', $totals->count) }}</td>
                    <td class="hidden px-4 py-3 text-right tabular-nums lg:table-cell">{{ Money::number($totals->subtotal) }}</td>
                    <td class="hidden px-4 py-3 text-right tabular-nums lg:table-cell">{{ Money::number($totals->discount) }}</td>
                    <td class="hidden px-4 py-3 text-right tabular-nums md:table-cell">{{ Money::number($totals->tax) }}</td>
                    <td class="px-4 py-3 text-right tabular-nums">{{ Money::format($totals->total) }}</td>
                    <td></td>
                </tr>
            </x-ui.table>
            @if ($purchases->hasPages())
                <div class="no-print border-t border-gray-100 px-4 py-3">{{ $purchases->links() }}</div>
            @endif
        @endif
    </x-ui.card>
</x-app-layout>
