<x-app-layout>
    <x-slot name="title">{{ __('Categories') }}</x-slot>

    <x-ui.page-header :title="__('Categories')" :description="__('Group products for the catalog and the POS.')">
        <x-slot:actions>
            @can('categories.create')
                <x-ui.button :href="route('categories.create')" icon="plus">{{ __('New Category') }}</x-ui.button>
            @endcan
        </x-slot:actions>
    </x-ui.page-header>

    <x-ui.card :padding="false">
        <form method="GET" class="flex flex-col gap-3 border-b border-gray-100 p-4 sm:flex-row sm:items-center">
            <div class="flex-1">
                <x-ui.input name="search" type="search" :value="$search" :placeholder="__('Search categories…')" :aria-label="__('Search')" />
            </div>
            <div class="sm:w-44">
                <x-ui.select name="status" :options="['active' => __('Active'), 'inactive' => __('Inactive')]" :selected="$status" :placeholder="__('All statuses')" :aria-label="__('Status')" />
            </div>
            <div class="flex gap-2">
                <x-ui.button type="submit" variant="secondary" icon="filter">{{ __('Filter') }}</x-ui.button>
                @if ($search || $status)
                    <x-ui.button :href="route('categories.index')" variant="ghost">{{ __('Clear') }}</x-ui.button>
                @endif
            </div>
        </form>

        @if ($categories->isEmpty())
            <x-ui.empty-state
                icon="categories"
                :title="$search || $status ? __('No matching categories') : __('No categories yet')"
                :description="$search || $status ? __('Try a different search or filter.') : __('Create your first category to organise products.')"
            />
        @else
            <x-ui.table>
                <x-slot:head>
                    <th class="px-4 py-3">{{ __('Category') }}</th>
                    <th class="px-4 py-3 text-right">{{ __('Products') }}</th>
                    <th class="hidden px-4 py-3 text-right md:table-cell">{{ __('Order') }}</th>
                    <th class="px-4 py-3">{{ __('Status') }}</th>
                    <th class="px-4 py-3"><span class="sr-only">{{ __('Actions') }}</span></th>
                </x-slot:head>

                @foreach ($categories as $category)
                    <tr class="hover:bg-gray-50">
                        <td class="px-4 py-3">
                            <div class="flex items-center gap-3">
                                <x-ui.thumbnail :src="$category->image_url" :emoji="$category->placeholder_icon" :color="$category->placeholder_color" icon="categories" size="sm" />
                                <div class="min-w-0">
                                    <a href="{{ route('categories.show', $category) }}" class="font-medium text-gray-900 hover:text-primary-600">{{ $category->name }}</a>
                                    <p class="truncate text-xs text-gray-500">{{ $category->slug }}</p>
                                </div>
                            </div>
                        </td>
                        <td class="px-4 py-3 text-right tabular-nums text-gray-700">{{ $category->products_count }}</td>
                        <td class="hidden px-4 py-3 text-right tabular-nums text-gray-500 md:table-cell">{{ $category->sort_order }}</td>
                        <td class="px-4 py-3"><x-ui.status-badge :active="$category->is_active" /></td>
                        <td class="px-4 py-3">
                            @include('categories._row-actions')
                        </td>
                    </tr>
                @endforeach
            </x-ui.table>

            @if ($categories->hasPages())
                <div class="border-t border-gray-100 px-4 py-3">{{ $categories->links() }}</div>
            @endif
        @endif
    </x-ui.card>
</x-app-layout>
