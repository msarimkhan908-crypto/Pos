@use('App\Support\Money')

<x-app-layout>
    <x-slot name="title">{{ __('Product Sales Report') }}</x-slot>

    <x-ui.page-header :title="__('Product Sales Report')" :description="__('What sold in the period. Revenue is after discounts, before tax.')" />

    @include('reports._toolbar', [
        'current' => 'reports.products',
        'selects' => [
            ['name' => 'category', 'label' => __('Category'), 'options' => $categories, 'selected' => $filters['category'] ?? null, 'placeholder' => __('All categories')],
        ],
    ])

    <x-ui.card :padding="false">
        @if ($products->isEmpty())
            <x-ui.empty-state icon="products" :title="__('No products sold in this period')" :description="__('Try another period.')" />
        @else
            <x-ui.table>
                <x-slot:head>
                    <th class="px-4 py-3">{{ __('Product') }}</th>
                    <th class="px-4 py-3 text-right">{{ __('Qty sold') }}</th>
                    <th class="hidden px-4 py-3 text-right md:table-cell">{{ __('Returned') }}</th>
                    <th class="hidden px-4 py-3 text-right sm:table-cell">{{ __('Net sold') }}</th>
                    <th class="px-4 py-3 text-right">{{ __('Sales') }}</th>
                    <th class="px-4 py-3 text-right">{{ __('Revenue') }}</th>
                </x-slot:head>
                @foreach ($products as $row)
                    <tr class="hover:bg-gray-50">
                        <td class="px-4 py-3 font-medium text-gray-900">{{ $row->product_name }}@if ($row->variant_name) <span class="font-normal text-gray-500">({{ $row->variant_name }})</span>@endif</td>
                        <td class="px-4 py-3 text-right tabular-nums">{{ (int) $row->quantity }}</td>
                        <td class="hidden px-4 py-3 text-right tabular-nums md:table-cell">{{ (int) $row->returned }}</td>
                        <td class="hidden px-4 py-3 text-right tabular-nums sm:table-cell">{{ max(0, (int) $row->quantity - (int) $row->returned) }}</td>
                        <td class="px-4 py-3 text-right tabular-nums">{{ (int) $row->sales_count }}</td>
                        <td class="whitespace-nowrap px-4 py-3 text-right font-medium tabular-nums">{{ Money::format($row->revenue) }}</td>
                    </tr>
                @endforeach
                {{-- Totals row --}}
                @if ($totals)
                    <tr class="border-t-2 border-gray-200 bg-gray-50 font-semibold" data-totals-row>
                        <td class="px-4 py-3 text-gray-700">{{ __('Total') }}</td>
                        <td class="px-4 py-3 text-right tabular-nums text-gray-900">{{ (int) $totals->qty }}</td>
                        <td class="hidden px-4 py-3 text-right tabular-nums text-gray-900 md:table-cell">{{ (int) $totals->returned }}</td>
                        <td class="hidden px-4 py-3 text-right tabular-nums text-gray-900 sm:table-cell">{{ max(0, (int) $totals->qty - (int) $totals->returned) }}</td>
                        <td class="px-4 py-3"></td>
                        <td class="whitespace-nowrap px-4 py-3 text-right tabular-nums text-gray-900">{{ Money::format($totals->revenue) }}</td>
                    </tr>
                @endif
            </x-ui.table>
            @if ($products->hasPages())
                <div class="no-print border-t border-gray-100 px-4 py-3">{{ $products->links() }}</div>
            @endif
        @endif
    </x-ui.card>
</x-app-layout>
