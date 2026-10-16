<x-app-layout>
    <x-slot name="title">{{ __('Kitchen Stations') }}</x-slot>

    <x-ui.page-header :title="__('Kitchen Stations')" :description="__('Where food is prepared. Assign a station to a category or product to send its items to the kitchen.')">
        <x-slot:actions>
            @can('settings.manage')
                <x-ui.button :href="route('kitchen.stations.create')" icon="plus">{{ __('New Station') }}</x-ui.button>
            @endcan
        </x-slot:actions>
    </x-ui.page-header>

    <x-ui.card :padding="false">
        @if ($stations->isEmpty())
            <x-ui.empty-state icon="kitchen" :title="__('No kitchen stations yet')" :description="__('Add stations such as Main Kitchen, Grill, Pizza, Drinks or Dessert.')" />
        @else
            <x-ui.table>
                <x-slot:head>
                    <th class="px-4 py-3">{{ __('Station') }}</th>
                    <th class="px-4 py-3 text-right">{{ __('Categories') }}</th>
                    <th class="px-4 py-3 text-right">{{ __('Products') }}</th>
                    <th class="px-4 py-3">{{ __('Status') }}</th>
                    <th class="px-4 py-3"><span class="sr-only">{{ __('Actions') }}</span></th>
                </x-slot:head>

                @foreach ($stations as $station)
                    <tr class="hover:bg-gray-50">
                        <td class="px-4 py-3">
                            <p class="font-medium text-gray-900">{{ $station->name }}</p>
                            @if ($station->description)
                                <p class="text-xs text-gray-500">{{ $station->description }}</p>
                            @endif
                        </td>
                        <td class="px-4 py-3 text-right tabular-nums text-gray-700">{{ $station->categories_count }}</td>
                        <td class="px-4 py-3 text-right tabular-nums text-gray-700">{{ $station->products_count }}</td>
                        <td class="px-4 py-3"><x-ui.status-badge :active="$station->is_active" /></td>
                        <td class="px-4 py-3">
                            @can('settings.manage')
                                <div class="flex items-center justify-end gap-1">
                                    <x-ui.icon-button :href="route('kitchen.stations.edit', $station)" icon="pencil" :label="__('Edit')" />
                                    <x-ui.icon-button icon="trash" variant="danger" :label="__('Delete')" :confirm="[
                                        'action' => route('kitchen.stations.destroy', $station),
                                        'title' => __('Delete :name?', ['name' => $station->name]),
                                        'message' => __('Categories and products using it will no longer be sent to this station.'),
                                    ]" />
                                </div>
                            @endcan
                        </td>
                    </tr>
                @endforeach
            </x-ui.table>
        @endif
    </x-ui.card>
</x-app-layout>
