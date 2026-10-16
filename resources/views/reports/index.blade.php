@use('App\Support\Money')
@use('App\Enums\PaymentMethod')

<x-app-layout>
    <x-slot name="title">{{ __('Reports') }}</x-slot>

    <x-ui.page-header :title="__('Reports')" :description="__('Figures from your recorded sales, returns, purchases and expenses.')" />

    @include('reports._toolbar', ['current' => 'reports.index'])

    @php
        $cards = [
            ['label' => __('Total sales'), 'value' => Money::format($summary['sales_total']), 'hint' => trans_choice(':count sale|:count sales', $summary['sales_count']), 'route' => 'reports.sales', 'key' => 'sales'],
            ['label' => __('Total returns'), 'value' => Money::format($summary['returns_total']), 'hint' => trans_choice(':count refund|:count refunds', $summary['returns_count']), 'route' => 'reports.returns', 'key' => 'returns'],
            ['label' => __('Net sales'), 'value' => Money::format($summary['net_sales']), 'hint' => __('Sales minus refunds'), 'route' => 'reports.sales', 'key' => 'net'],
            ['label' => __('Total purchases'), 'value' => Money::format($summary['purchases_total']), 'hint' => trans_choice(':count completed purchase|:count completed purchases', $summary['purchases_count']), 'route' => 'reports.purchases', 'key' => 'purchases'],
            ['label' => __('Total expenses'), 'value' => Money::format($summary['expenses_total']), 'hint' => trans_choice(':count expense|:count expenses', $summary['expenses_count']), 'route' => 'reports.expenses', 'key' => 'expenses'],
            ['label' => __('Customers'), 'value' => number_format($summary['customers_served']), 'hint' => __(':total on record', ['total' => number_format($summary['customers_total'])]), 'route' => 'reports.customers', 'key' => 'customers'],
        ];
    @endphp

    <div class="grid grid-cols-1 gap-4 sm:grid-cols-2 xl:grid-cols-3">
        @foreach ($cards as $card)
            <a href="{{ route($card['route'], $range->query()) }}" class="rounded-xl bg-white p-5 shadow-sm ring-1 ring-gray-200 transition hover:ring-primary-300" data-summary="{{ $card['key'] }}">
                <p class="text-sm font-medium text-gray-500">{{ $card['label'] }}</p>
                <p class="mt-1 text-2xl font-bold tabular-nums text-gray-900">{{ $card['value'] }}</p>
                <p class="mt-1 text-xs text-gray-500">{{ $card['hint'] }}</p>
            </a>
        @endforeach
    </div>

    <div class="mt-6 grid grid-cols-1 gap-6 lg:grid-cols-2">
        <x-ui.card :title="__('Sales by payment method')">
            @if (empty($summary['payments']))
                <p class="text-sm text-gray-500">{{ __('No sales in this period.') }}</p>
            @else
                <dl class="space-y-2 text-sm">
                    @foreach ($summary['payments'] as $method => $total)
                        <div class="flex justify-between"><dt class="text-gray-600">{{ PaymentMethod::tryFrom($method)?->label() ?? $method }}</dt><dd class="font-medium tabular-nums text-gray-900">{{ Money::format($total) }}</dd></div>
                    @endforeach
                    <div class="flex justify-between border-t border-gray-100 pt-2"><dt class="text-gray-600">{{ __('Discounts given') }}</dt><dd class="tabular-nums text-gray-900">{{ Money::format($summary['discount_total']) }}</dd></div>
                    <div class="flex justify-between"><dt class="text-gray-600">{{ __('Tax collected') }}</dt><dd class="tabular-nums text-gray-900">{{ Money::format($summary['tax_total']) }}</dd></div>
                </dl>
            @endif
        </x-ui.card>

        <x-ui.card :title="__('Expenses by category')">
            @if (empty($summary['expenses_by_category']))
                <p class="text-sm text-gray-500">{{ __('No expenses in this period.') }}</p>
            @else
                <dl class="space-y-2 text-sm">
                    @foreach ($summary['expenses_by_category'] as $name => $total)
                        <div class="flex justify-between"><dt class="text-gray-600">{{ $name }}</dt><dd class="font-medium tabular-nums text-gray-900">{{ Money::format($total) }}</dd></div>
                    @endforeach
                </dl>
            @endif
        </x-ui.card>
    </div>
</x-app-layout>
