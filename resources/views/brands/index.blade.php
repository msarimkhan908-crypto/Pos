<x-app-layout>
    <x-slot name="title">{{ __('Brands') }}</x-slot>

    <x-ui.page-header :title="__('Brands')" :description="__('Manufacturers and labels products can belong to.')">
        <x-slot:actions>
            @can('brands.create')
                <x-ui.button :href="route('brands.create')" icon="plus">{{ __('New Brand') }}</x-ui.button>
            @endcan
        </x-slot:actions>
    </x-ui.page-header>

    <x-ui.card :padding="false">
        <form method="GET" class="flex flex-col gap-3 border-b border-gray-100 p-4 sm:flex-row sm:items-center">
            <div class="flex-1">
                <x-ui.input name="search" type="search" :value="$search" :placeholder="__('Search brands…')" :aria-label="__('Search')" />
            </div>
            <div class="sm:w-44">
                <x-ui.select name="status" :options="['active' => __('Active'), 'inactive' => __('Inactive')]" :selected="$status" :placeholder="__('All statuses')" :aria-label="__('Status')" />
            </div>
            <div class="flex gap-2">
                <x-ui.button type="submit" variant="secondary" icon="filter">{{ __('Filter') }}</x-ui.button>
                @if ($search || $status)
                    <x-ui.button :href="route('brands.index')" variant="ghost">{{ __('Clear') }}</x-ui.button>
                @endif
            </div>
        </form>

        @if ($brands->isEmpty())
            <x-ui.empty-state
                icon="brands"
                :title="$search || $status ? __('No matching brands') : __('No brands yet')"
                :description="$search || $status ? __('Try a different search or filter.') : __('Brands are optional. Add one to group products by manufacturer.')"
            />
        @else
            <x-ui.table>
                <x-slot:head>
                    <th class="px-4 py-3">{{ __('Brand') }}</th>
                    <th class="px-4 py-3 text-right">{{ __('Products') }}</th>
                    <th class="px-4 py-3">{{ __('Status') }}</th>
                    <th class="px-4 py-3"><span class="sr-only">{{ __('Actions') }}</span></th>
                </x-slot:head>

                @foreach ($brands as $brand)
                    <tr class="hover:bg-gray-50">
                        <td class="px-4 py-3">
                            <div class="flex items-center gap-3">
                                <x-ui.thumbnail :src="$brand->logo_url" icon="brands" size="sm" />
                                <div class="min-w-0">
                                    <a href="{{ route('brands.show', $brand) }}" class="font-medium text-gray-900 hover:text-primary-600">{{ $brand->name }}</a>
                                    <p class="truncate text-xs text-gray-500">{{ $brand->slug }}</p>
                                </div>
                            </div>
                        </td>
                        <td class="px-4 py-3 text-right tabular-nums text-gray-700">{{ $brand->products_count }}</td>
                        <td class="px-4 py-3"><x-ui.status-badge :active="$brand->is_active" /></td>
                        <td class="px-4 py-3">
                            <div class="flex items-center justify-end gap-1">
                                <x-ui.icon-button :href="route('brands.show', $brand)" icon="eye" :label="__('View')" />
                                @can('brands.edit')
                                    <x-ui.icon-button :href="route('brands.edit', $brand)" icon="pencil" :label="__('Edit')" />
                                    <form method="POST" action="{{ route('brands.toggle', $brand) }}">
                                        @csrf
                                        @method('PATCH')
                                        <x-ui.icon-button type="submit" icon="power" :label="$brand->is_active ? __('Deactivate') : __('Activate')" />
                                    </form>
                                @endcan
                                @can('brands.delete')
                                    <x-ui.icon-button
                                        icon="trash"
                                        variant="danger"
                                        :label="__('Delete')"
                                        :confirm="[
                                            'action' => route('brands.destroy', $brand),
                                            'title' => __('Delete :name?', ['name' => $brand->name]),
                                            'message' => __('Brands that still have products cannot be deleted. This cannot be undone.'),
                                        ]"
                                    />
                                @endcan
                            </div>
                        </td>
                    </tr>
                @endforeach
            </x-ui.table>

            @if ($brands->hasPages())
                <div class="border-t border-gray-100 px-4 py-3">{{ $brands->links() }}</div>
            @endif
        @endif
    </x-ui.card>
</x-app-layout>
