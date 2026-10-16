@use('App\Support\Money')
@use('App\Support\Format')
@use('App\Enums\MoneyMethod')
@use('App\Enums\ReturnStatus')

<x-app-layout>
    <x-slot name="title">{{ __('Returns Report') }}</x-slot>

    <x-ui.page-header :title="__('Returns Report')" :description="__('Returns by return date.')" />

    @include('reports._toolbar', ['current' => 'reports.returns', 'selects' => [
        ['name' => 'status', 'label' => __('Status'), 'options' => ReturnStatus::options(), 'selected' => $filters['status'] ?? null, 'placeholder' => __('All statuses')],
        ['name' => 'method', 'label' => __('Refund method'), 'options' => MoneyMethod::options(), 'selected' => $filters['method'] ?? null, 'placeholder' => __('All methods')],
    ]])

    <x-ui.card :padding="false">
        @if ($returns->isEmpty())
            <x-ui.empty-state icon="history" :title="__('No returns in this period')" :description="__('Try another period or clear the filters.')" />
        @else
            <x-ui.table>
                <x-slot:head>
                    <th class="px-4 py-3">{{ __('Return') }}</th>
                    <th class="px-4 py-3">{{ __('Sale') }}</th>
                    <th class="px-4 py-3">{{ __('Date') }}</th>
                    <th class="hidden px-4 py-3 md:table-cell">{{ __('Customer') }}</th>
                    <th class="px-4 py-3 text-right">{{ __('Refund') }}</th>
                    <th class="hidden px-4 py-3 md:table-cell">{{ __('Method') }}</th>
                    <th class="px-4 py-3">{{ __('Status') }}</th>
                </x-slot:head>
                @foreach ($returns as $return)
                    <tr class="hover:bg-gray-50">
                        <td class="px-4 py-3 font-medium text-gray-900">
                            @can('returns.view')<a href="{{ route('returns.show', $return) }}" class="hover:text-primary-600">{{ $return->return_number }}</a>@else{{ $return->return_number }}@endcan
                        </td>
                        <td class="px-4 py-3 text-gray-700">{{ $return->sale?->invoice_number }}</td>
                        <td class="whitespace-nowrap px-4 py-3 text-gray-600">{{ Format::date($return->return_date) }}</td>
                        <td class="hidden px-4 py-3 text-gray-700 md:table-cell">{{ $return->customerLabel() }}</td>
                        <td class="whitespace-nowrap px-4 py-3 text-right font-medium tabular-nums">{{ Money::format($return->refund_amount) }}</td>
                        <td class="hidden px-4 py-3 text-gray-600 md:table-cell">{{ $return->refund_method->label() }}</td>
                        <td class="px-4 py-3"><x-ui.badge :color="$return->status->color()" dot>{{ $return->status->label() }}</x-ui.badge></td>
                    </tr>
                @endforeach
                <tr class="bg-gray-50 font-semibold" data-totals>
                    <td class="px-4 py-3" colspan="3">{{ trans_choice(':count return|:count returns', $totals->count) }}</td>
                    <td class="hidden md:table-cell"></td>
                    <td class="px-4 py-3 text-right tabular-nums">{{ Money::format($totals->total) }}</td>
                    <td colspan="2"></td>
                </tr>
            </x-ui.table>
            @if ($returns->hasPages())
                <div class="no-print border-t border-gray-100 px-4 py-3">{{ $returns->links() }}</div>
            @endif
        @endif
    </x-ui.card>
</x-app-layout>
