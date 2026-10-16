@use('App\Support\Money')

@php
    $hasFilters = collect($filters)->filter()->isNotEmpty();
@endphp

<x-app-layout>
    <x-slot name="title">{{ __('Products') }}</x-slot>

    <x-ui.page-header :title="__('Products')" :description="__('Everything you sell, with pricing, variants and add-ons.')">
        <x-slot:actions>
            @can('products.create')
                <x-ui.button :href="route('products.create')" icon="plus">{{ __('New Product') }}</x-ui.button>
            @endcan
        </x-slot:actions>
    </x-ui.page-header>

    <x-ui.card :padding="false">
        <form method="GET" class="grid grid-cols-1 gap-3 border-b border-gray-100 p-4 sm:grid-cols-2 lg:grid-cols-5">
            <div class="sm:col-span-2">
                <x-ui.input name="search" type="search" :value="$filters['search']" :placeholder="__('Name, SKU or barcode…')" :aria-label="__('Search')" />
            </div>
            <x-ui.select name="category" :options="$categories->pluck('name', 'id')" :selected="$filters['category']" :placeholder="__('All categories')" :aria-label="__('Category')" />
            <x-ui.select name="brand" :options="$brands->pluck('name', 'id')" :selected="$filters['brand']" :placeholder="__('All brands')" :aria-label="__('Brand')" />
            <x-ui.select name="status" :options="['active' => __('Active'), 'inactive' => __('Inactive')]" :selected="$filters['status']" :placeholder="__('All statuses')" :aria-label="__('Status')" />
            <div class="flex gap-2 sm:col-span-2 lg:col-span-5 lg:justify-end">
                @if ($hasFilters)
                    <x-ui.button :href="route('products.index')" variant="ghost">{{ __('Clear filters') }}</x-ui.button>
                @endif
                <x-ui.button type="submit" variant="secondary" icon="filter">{{ __('Apply') }}</x-ui.button>
            </div>
        </form>

        @if ($products->isEmpty())
            <x-ui.empty-state
                icon="products"
                :title="$hasFilters ? __('No matching products') : __('No products yet')"
                :description="$hasFilters ? __('Try a different search or clear the filters.') : __('Add your first product to start building the catalog.')"
            >
                @if (! $hasFilters)
                    @can('products.create')
                        <x-ui.button :href="route('products.create')" icon="plus" size="sm">{{ __('New Product') }}</x-ui.button>
                    @endcan
                @endif
            </x-ui.empty-state>
        @else
            <x-ui.table>
                <x-slot:head>
                    <th class="px-4 py-3">{{ __('Product') }}</th>
                    <th class="hidden px-4 py-3 md:table-cell">{{ __('Category') }}</th>
                    <th class="px-4 py-3 text-right">{{ __('Price') }}</th>
                    <th class="hidden px-4 py-3 sm:table-cell">{{ __('Status') }}</th>
                    <th class="px-4 py-3"><span class="sr-only">{{ __('Actions') }}</span></th>
                </x-slot:head>

                @foreach ($products as $product)
                    <tr class="hover:bg-gray-50">
                        <td class="px-4 py-3">
                            <div class="flex items-center gap-3">
                                <x-ui.thumbnail :src="$product->image_url" :alt="$product->name" :emoji="$product->placeholder_icon" :color="$product->placeholder_color" icon="products" />
                                <div class="min-w-0">
                                    <a href="{{ route('products.show', $product) }}" class="font-medium text-gray-900 hover:text-primary-600">{{ $product->name }}</a>
                                    <p class="truncate text-xs text-gray-500">
                                        {{ $product->sku }}
                                        @if ($product->barcode) · {{ $product->barcode }} @endif
                                    </p>
                                    @if ($product->variants_count > 0)
                                        <p class="text-xs text-primary-600">{{ trans_choice(':count variant|:count variants', $product->variants_count) }}</p>
                                    @endif
                                    @if ($product->is_deal)
                                        <x-ui.badge color="info" size="sm">{{ __('Deal') }}</x-ui.badge>
                                    @endif
                                </div>
                            </div>
                        </td>
                        <td class="hidden px-4 py-3 text-gray-600 md:table-cell">
                            {{ $product->category?->name ?? '—' }}
                            @if ($product->brand)
                                <p class="text-xs text-gray-400">{{ $product->brand->name }}</p>
                            @endif
                        </td>
                        <td class="whitespace-nowrap px-4 py-3 text-right tabular-nums">
                            @if ($product->discount_type)
                                <span class="font-medium text-gray-900">{{ Money::format($product->discountedPrice()) }}</span>
                                <p class="text-xs text-gray-400 line-through">{{ Money::format($product->selling_price) }}</p>
                            @else
                                <span class="font-medium text-gray-900">{{ Money::format($product->selling_price) }}</span>
                            @endif
                        </td>
                        <td class="hidden px-4 py-3 sm:table-cell"><x-ui.status-badge :active="$product->is_active" /></td>
                        <td class="px-4 py-3">
                            <div class="flex items-center justify-end gap-1">
                                <x-ui.icon-button :href="route('products.show', $product)" icon="eye" :label="__('View')" />
                                @can('products.edit')
                                    <x-ui.icon-button :href="route('products.edit', $product)" icon="pencil" :label="__('Edit')" />
                                @endcan
                            </div>
                        </td>
                    </tr>
                @endforeach
            </x-ui.table>

            <div class="flex flex-col gap-2 border-t border-gray-100 px-4 py-3 text-sm text-gray-500 sm:flex-row sm:items-center sm:justify-between">
                <p>{{ __('Showing :from–:to of :total', ['from' => $products->firstItem(), 'to' => $products->lastItem(), 'total' => $products->total()]) }}</p>
                @if ($products->hasPages())
                    {{ $products->links() }}
                @endif
            </div>
        @endif
    </x-ui.card>
</x-app-layout>
