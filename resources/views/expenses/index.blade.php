@use('App\Enums\MoneyMethod')
@use('App\Support\Money')
@use('App\Support\Format')

<x-app-layout>
    <x-slot name="title">{{ __('Expenses') }}</x-slot>

    <x-ui.page-header :title="__('Expenses')" :description="__('Rent, bills, salaries and other business costs.')">
        <x-slot:actions>
            @can('expenses.view')
                <x-ui.button :href="route('expense-categories.index')" variant="secondary" icon="categories">{{ __('Categories') }}</x-ui.button>
            @endcan
            @can('expenses.create')
                <x-ui.button :href="route('expenses.create')" icon="plus">{{ __('New Expense') }}</x-ui.button>
            @endcan
        </x-slot:actions>
    </x-ui.page-header>

    <x-ui.card :padding="false">
        <form method="GET" class="grid gap-3 border-b border-gray-100 p-4 sm:grid-cols-2 lg:grid-cols-[minmax(0,1fr)_11rem_10rem_10rem_10rem_auto] lg:items-end">
            <x-ui.input name="search" type="search" :label="__('Search')" :value="$filters['search'] ?? ''" :placeholder="__('Number, description or reference')" />
            <x-ui.select name="category" :label="__('Category')" :options="$categories->all()" :selected="$filters['category'] ?? null" :placeholder="__('All categories')" />
            <x-ui.select name="method" :label="__('Payment')" :options="MoneyMethod::options()" :selected="$filters['method'] ?? null" :placeholder="__('All methods')" />
            <x-ui.input name="from" type="date" :label="__('From')" :value="$filters['from'] ?? ''" />
            <x-ui.input name="to" type="date" :label="__('To')" :value="$filters['to'] ?? ''" />
            <div class="flex gap-2">
                <x-ui.button type="submit" variant="secondary" icon="filter">{{ __('Filter') }}</x-ui.button>
                @if (array_filter($filters))
                    <x-ui.button :href="route('expenses.index')" variant="ghost">{{ __('Clear') }}</x-ui.button>
                @endif
            </div>
        </form>

        @if ($expenses->isEmpty())
            <x-ui.empty-state icon="banknote"
                :title="array_filter($filters) ? __('No matching expenses') : __('No expenses yet')"
                :description="array_filter($filters) ? __('Try a different search or filter.') : __('Record rent, bills and other costs to see them in reports.')" />
        @else
            <x-ui.table>
                <x-slot:head>
                    <th class="px-4 py-3">{{ __('Expense') }}</th>
                    <th class="px-4 py-3">{{ __('Category') }}</th>
                    <th class="px-4 py-3">{{ __('Date') }}</th>
                    <th class="hidden px-4 py-3 md:table-cell">{{ __('Description') }}</th>
                    <th class="hidden px-4 py-3 lg:table-cell">{{ __('Payment') }}</th>
                    <th class="px-4 py-3 text-right">{{ __('Amount') }}</th>
                    <th class="px-4 py-3"><span class="sr-only">{{ __('Actions') }}</span></th>
                </x-slot:head>
                @foreach ($expenses as $expense)
                    <tr class="hover:bg-gray-50">
                        <td class="px-4 py-3 font-medium text-gray-900"><a href="{{ route('expenses.show', $expense) }}" class="hover:text-primary-600">{{ $expense->expense_number }}</a></td>
                        <td class="px-4 py-3 text-gray-700">{{ $expense->category->name }}</td>
                        <td class="whitespace-nowrap px-4 py-3 text-gray-600">{{ Format::date($expense->expense_date) }}</td>
                        <td class="hidden max-w-xs truncate px-4 py-3 text-gray-600 md:table-cell">{{ $expense->description ?? '—' }}</td>
                        <td class="hidden px-4 py-3 text-gray-600 lg:table-cell">{{ $expense->payment_method->label() }}</td>
                        <td class="whitespace-nowrap px-4 py-3 text-right font-medium tabular-nums text-gray-900">{{ Money::format($expense->amount) }}</td>
                        <td class="px-4 py-3">
                            <div class="flex items-center justify-end gap-1">
                                <x-ui.icon-button :href="route('expenses.show', $expense)" icon="eye" :label="__('View')" />
                                @can('expenses.edit')
                                    <x-ui.icon-button :href="route('expenses.edit', $expense)" icon="pencil" :label="__('Edit')" />
                                @endcan
                                @can('expenses.delete')
                                    <x-ui.icon-button icon="trash" variant="danger" :label="__('Delete')" :confirm="[
                                        'action' => route('expenses.destroy', $expense),
                                        'title' => __('Delete :number?', ['number' => $expense->expense_number]),
                                        'message' => __('This cannot be undone.'),
                                    ]" />
                                @endcan
                            </div>
                        </td>
                    </tr>
                @endforeach
            </x-ui.table>
            <div class="flex flex-col gap-2 border-t border-gray-100 px-4 py-3 sm:flex-row sm:items-center sm:justify-between">
                <p class="text-sm text-gray-600">{{ trans_choice(':count expense|:count expenses', $expenses->total()) }} · {{ __('Total') }} <span class="font-semibold tabular-nums text-gray-900" data-expense-total>{{ Money::format($total) }}</span></p>
                @if ($expenses->hasPages())
                    {{ $expenses->links() }}
                @endif
            </div>
        @endif
    </x-ui.card>
</x-app-layout>
