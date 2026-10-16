@use('App\Support\Money')
@use('App\Support\Format')
@use('App\Enums\MoneyMethod')

<x-app-layout>
    <x-slot name="title">{{ __('Expense Report') }}</x-slot>

    <x-ui.page-header :title="__('Expense Report')" :description="__('Expenses by expense date.')" />

    @include('reports._toolbar', ['current' => 'reports.expenses', 'selects' => [
        ['name' => 'category', 'label' => __('Category'), 'options' => $categories->all(), 'selected' => $filters['category'] ?? null, 'placeholder' => __('All categories')],
        ['name' => 'method', 'label' => __('Payment'), 'options' => MoneyMethod::options(), 'selected' => $filters['method'] ?? null, 'placeholder' => __('All methods')],
    ]])

    <x-ui.card :padding="false">
        @if ($expenses->isEmpty())
            <x-ui.empty-state icon="banknote" :title="__('No expenses in this period')" :description="__('Try another period or clear the filters.')" />
        @else
            <x-ui.table>
                <x-slot:head>
                    <th class="px-4 py-3">{{ __('Expense') }}</th>
                    <th class="px-4 py-3">{{ __('Category') }}</th>
                    <th class="px-4 py-3">{{ __('Date') }}</th>
                    <th class="px-4 py-3 text-right">{{ __('Amount') }}</th>
                    <th class="hidden px-4 py-3 md:table-cell">{{ __('Payment') }}</th>
                    <th class="hidden px-4 py-3 lg:table-cell">{{ __('Description') }}</th>
                </x-slot:head>
                @foreach ($expenses as $expense)
                    <tr class="hover:bg-gray-50">
                        <td class="px-4 py-3 font-medium text-gray-900">
                            @can('expenses.view')<a href="{{ route('expenses.show', $expense) }}" class="hover:text-primary-600">{{ $expense->expense_number }}</a>@else{{ $expense->expense_number }}@endcan
                        </td>
                        <td class="px-4 py-3 text-gray-700">{{ $expense->category?->name }}</td>
                        <td class="whitespace-nowrap px-4 py-3 text-gray-600">{{ Format::date($expense->expense_date) }}</td>
                        <td class="whitespace-nowrap px-4 py-3 text-right font-medium tabular-nums">{{ Money::format($expense->amount) }}</td>
                        <td class="hidden px-4 py-3 text-gray-600 md:table-cell">{{ $expense->payment_method->label() }}</td>
                        <td class="hidden max-w-xs truncate px-4 py-3 text-gray-600 lg:table-cell">{{ $expense->description ?? '—' }}</td>
                    </tr>
                @endforeach
                <tr class="bg-gray-50 font-semibold" data-totals>
                    <td class="px-4 py-3" colspan="3">{{ trans_choice(':count expense|:count expenses', $totals->count) }}</td>
                    <td class="px-4 py-3 text-right tabular-nums">{{ Money::format($totals->total) }}</td>
                    <td colspan="2"></td>
                </tr>
            </x-ui.table>
            @if ($expenses->hasPages())
                <div class="no-print border-t border-gray-100 px-4 py-3">{{ $expenses->links() }}</div>
            @endif
        @endif
    </x-ui.card>
</x-app-layout>
