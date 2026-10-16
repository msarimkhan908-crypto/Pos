<x-app-layout>
    <x-slot name="title">{{ $category->name }}</x-slot>

    <x-ui.page-header :title="$category->name" :description="$category->slug">
        <x-slot:breadcrumbs>
            <a href="{{ route('categories.index') }}" class="hover:text-gray-700">{{ __('Categories') }}</a>
        </x-slot:breadcrumbs>
        <x-slot:actions>
            @can('categories.edit')
                <form method="POST" action="{{ route('categories.toggle', $category) }}">
                    @csrf
                    @method('PATCH')
                    <x-ui.button type="submit" variant="secondary" icon="power">
                        {{ $category->is_active ? __('Deactivate') : __('Activate') }}
                    </x-ui.button>
                </form>
                <x-ui.button :href="route('categories.edit', $category)" icon="pencil">{{ __('Edit') }}</x-ui.button>
            @endcan
        </x-slot:actions>
    </x-ui.page-header>

    <div class="grid grid-cols-1 gap-6 lg:grid-cols-3">
        <x-ui.card :title="__('Details')">
            <div class="space-y-4">
                <x-ui.thumbnail :src="$category->image_url" :alt="$category->name" :emoji="$category->placeholder_icon" :color="$category->placeholder_color" icon="categories" size="xl" class="w-full" />
                <dl class="space-y-3 text-sm">
                    <div class="flex justify-between gap-4"><dt class="text-gray-500">{{ __('Status') }}</dt><dd><x-ui.status-badge :active="$category->is_active" /></dd></div>
                    <div class="flex justify-between gap-4"><dt class="text-gray-500">{{ __('Products') }}</dt><dd class="font-medium tabular-nums text-gray-900">{{ $category->products_count }}</dd></div>
                    <div class="flex justify-between gap-4"><dt class="text-gray-500">{{ __('Display order') }}</dt><dd class="tabular-nums text-gray-900">{{ $category->sort_order }}</dd></div>
                    <div class="flex justify-between gap-4"><dt class="text-gray-500">{{ __('Updated') }}</dt><dd class="text-gray-900">{{ $category->updated_at->diffForHumans() }}</dd></div>
                </dl>
                @if ($category->description)
                    <p class="border-t border-gray-100 pt-4 text-sm leading-6 text-gray-600">{{ $category->description }}</p>
                @endif
            </div>
        </x-ui.card>

        <x-ui.card class="lg:col-span-2" :title="__('Products')" :description="trans_choice(':count product|:count products', $category->products_count)" :padding="false">
            @if ($category->products_count > 0 && auth()->user()->can('products.view'))
                <x-slot:actions>
                    <x-ui.button :href="route('products.index', ['category' => $category->id])" variant="ghost" size="sm">
                        {{ __('View all') }} <x-icon name="arrow-right" class="size-3.5" />
                    </x-ui.button>
                </x-slot:actions>
            @endif

            @forelse ($products as $product)
                <div class="flex items-center gap-3 border-b border-gray-100 px-5 py-3 last:border-b-0">
                    <x-ui.thumbnail :src="$product->image_url" :emoji="$product->placeholder_icon" :color="$product->placeholder_color" icon="products" size="sm" />
                    <div class="min-w-0 flex-1">
                        @can('products.view')
                            <a href="{{ route('products.show', $product) }}" class="truncate text-sm font-medium text-gray-900 hover:text-primary-600">{{ $product->name }}</a>
                        @else
                            <p class="truncate text-sm font-medium text-gray-900">{{ $product->name }}</p>
                        @endcan
                        <p class="text-xs text-gray-500">{{ $product->sku }}</p>
                    </div>
                    <span class="text-sm tabular-nums text-gray-700">{{ \App\Support\Money::format($product->selling_price) }}</span>
                    <x-ui.status-badge :active="$product->is_active" />
                </div>
            @empty
                <x-ui.empty-state icon="products" :title="__('No products in this category')" />
            @endforelse
        </x-ui.card>
    </div>

    @can('categories.delete')
        <div class="mt-6 flex justify-end">
            <x-ui.button
                variant="ghost"
                icon="trash"
                class="text-red-600 hover:bg-red-50"
                :confirm="[
                    'action' => route('categories.destroy', $category),
                    'title' => __('Delete :name?', ['name' => $category->name]),
                    'message' => __('Categories that still have products cannot be deleted. This cannot be undone.'),
                ]"
            >{{ __('Delete category') }}</x-ui.button>
        </div>
    @endcan
</x-app-layout>
