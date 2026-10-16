@use('App\Support\Money')

<x-app-layout>
    <x-slot name="title">{{ __('Modifiers') }}</x-slot>

    <x-ui.page-header :title="__('Modifiers')" :description="__('Add-on groups such as extra toppings, attached to products.')">
        <x-slot:actions>
            @can('products.create')
                <x-ui.button :href="route('modifier-groups.create')" icon="plus">{{ __('New Modifier Group') }}</x-ui.button>
            @endcan
        </x-slot:actions>
    </x-ui.page-header>

    <x-ui.card :padding="false">
        <form method="GET" class="flex flex-col gap-3 border-b border-gray-100 p-4 sm:flex-row sm:items-center">
            <div class="flex-1">
                <x-ui.input name="search" type="search" :value="$search" :placeholder="__('Search modifier groups…')" :aria-label="__('Search')" />
            </div>
            <div class="flex gap-2">
                <x-ui.button type="submit" variant="secondary" icon="filter">{{ __('Search') }}</x-ui.button>
                @if ($search)
                    <x-ui.button :href="route('modifier-groups.index')" variant="ghost">{{ __('Clear') }}</x-ui.button>
                @endif
            </div>
        </form>

        @if ($groups->isEmpty())
            <x-ui.empty-state
                icon="modifiers"
                :title="$search ? __('No matching modifier groups') : __('No modifier groups yet')"
                :description="$search ? __('Try a different search.') : __('Create a group like “Burger Add-ons” with options such as Cheese +100.')"
            />
        @else
            <x-ui.table>
                <x-slot:head>
                    <th class="px-4 py-3">{{ __('Group') }}</th>
                    <th class="px-4 py-3">{{ __('Options') }}</th>
                    <th class="hidden px-4 py-3 text-right md:table-cell">{{ __('Products') }}</th>
                    <th class="hidden px-4 py-3 sm:table-cell">{{ __('Status') }}</th>
                    <th class="px-4 py-3"><span class="sr-only">{{ __('Actions') }}</span></th>
                </x-slot:head>

                @foreach ($groups as $group)
                    <tr class="align-top hover:bg-gray-50">
                        <td class="px-4 py-3">
                            <p class="font-medium text-gray-900">{{ $group->name }}</p>
                            <p class="text-xs text-gray-500">{{ $group->selectionRule() }}</p>
                        </td>
                        <td class="px-4 py-3">
                            <ul class="flex flex-wrap gap-1.5">
                                @foreach ($group->modifiers->take(6) as $modifier)
                                    <li @class(['rounded border px-2 py-0.5 text-xs', 'border-gray-200 text-gray-700' => $modifier->is_active, 'border-dashed border-gray-200 text-gray-400' => ! $modifier->is_active])>
                                        {{ $modifier->name }} <span class="tabular-nums text-gray-500">+{{ Money::format($modifier->price) }}</span>
                                    </li>
                                @endforeach
                                @if ($group->modifiers->count() > 6)
                                    <li class="px-1 py-0.5 text-xs text-gray-500">{{ __('+:count more', ['count' => $group->modifiers->count() - 6]) }}</li>
                                @endif
                            </ul>
                        </td>
                        <td class="hidden px-4 py-3 text-right tabular-nums text-gray-700 md:table-cell">{{ $group->products_count }}</td>
                        <td class="hidden px-4 py-3 sm:table-cell"><x-ui.status-badge :active="$group->is_active" /></td>
                        <td class="px-4 py-3">
                            <div class="flex items-center justify-end gap-1">
                                @can('products.edit')
                                    <x-ui.icon-button :href="route('modifier-groups.edit', $group)" icon="pencil" :label="__('Edit')" />
                                @endcan
                                @can('products.delete')
                                    <x-ui.icon-button
                                        icon="trash"
                                        variant="danger"
                                        :label="__('Delete')"
                                        :confirm="[
                                            'action' => route('modifier-groups.destroy', $group),
                                            'title' => __('Delete :name?', ['name' => $group->name]),
                                            'message' => trans_choice('It will be removed from :count product.|It will be removed from :count products.', $group->products_count),
                                        ]"
                                    />
                                @endcan
                            </div>
                        </td>
                    </tr>
                @endforeach
            </x-ui.table>

            @if ($groups->hasPages())
                <div class="border-t border-gray-100 px-4 py-3">{{ $groups->links() }}</div>
            @endif
        @endif
    </x-ui.card>
</x-app-layout>
