@use('App\Support\Money')

<x-app-layout>
    <x-slot name="title">{{ __('Expense Categories') }}</x-slot>

    <x-ui.page-header :title="__('Expense Categories')" :description="__('Group expenses such as Rent, Electricity, Internet or Salaries.')">
        <x-slot:breadcrumbs>
            <a href="{{ route('expenses.index') }}" class="hover:text-gray-700">{{ __('Expenses') }}</a>
        </x-slot:breadcrumbs>
        <x-slot:actions>
            @can('expenses.edit')
                <x-ui.button :href="route('expense-categories.create')" icon="plus">{{ __('New Category') }}</x-ui.button>
            @endcan
        </x-slot:actions>
    </x-ui.page-header>

    <x-ui.card :padding="false">
        @if ($categories->isEmpty())
            <x-ui.empty-state icon="categories" :title="__('No expense categories yet')" :description="__('Create the categories you need, e.g. Rent, Electricity, Internet, Salaries, Transportation, Maintenance, Supplies.')" />
        @else
            <x-ui.table>
                <x-slot:head>
                    <th class="px-4 py-3">{{ __('Category') }}</th>
                    <th class="px-4 py-3 text-right">{{ __('Expenses') }}</th>
                    <th class="px-4 py-3 text-right">{{ __('Total') }}</th>
                    <th class="px-4 py-3">{{ __('Status') }}</th>
                    <th class="px-4 py-3"><span class="sr-only">{{ __('Actions') }}</span></th>
                </x-slot:head>
                @foreach ($categories as $category)
                    <tr class="hover:bg-gray-50">
                        <td class="px-4 py-3">
                            <p class="font-medium text-gray-900">{{ $category->name }}</p>
                            @if ($category->description)
                                <p class="text-xs text-gray-500">{{ $category->description }}</p>
                            @endif
                        </td>
                        <td class="px-4 py-3 text-right tabular-nums">{{ $category->expenses_count }}</td>
                        <td class="px-4 py-3 text-right tabular-nums">{{ Money::format($category->expenses_sum_amount ?? 0) }}</td>
                        <td class="px-4 py-3"><x-ui.status-badge :active="$category->isActive()" /></td>
                        <td class="px-4 py-3">
                            <div class="flex items-center justify-end gap-1">
                                @can('expenses.edit')
                                    <x-ui.icon-button :href="route('expense-categories.edit', $category)" icon="pencil" :label="__('Edit')" />
                                @endcan
                                @can('expenses.delete')
                                    <x-ui.icon-button icon="trash" variant="danger" :label="__('Delete')" :confirm="[
                                        'action' => route('expense-categories.destroy', $category),
                                        'title' => __('Delete :name?', ['name' => $category->name]),
                                        'message' => __('Categories with expenses cannot be deleted; deactivate them instead.'),
                                    ]" />
                                @endcan
                            </div>
                        </td>
                    </tr>
                @endforeach
            </x-ui.table>
        @endif
    </x-ui.card>
</x-app-layout>
