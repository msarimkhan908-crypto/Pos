@use('App\Support\Money')

@php
    /** @var \App\Models\Product $product */
    $hasVariants = $product->variants->isNotEmpty();
    $finalPrice = $product->discountedPrice();
    $margin = $finalPrice > 0
        ? ($finalPrice - (float) $product->purchase_price) / $finalPrice * 100
        : null;
@endphp

<x-app-layout>
    <x-slot name="title">{{ $product->name }}</x-slot>

    <x-ui.page-header :title="$product->name" :description="$product->sku">
        <x-slot:breadcrumbs>
            <a href="{{ route('products.index') }}" class="hover:text-gray-700">{{ __('Products') }}</a>
        </x-slot:breadcrumbs>
        <x-slot:actions>
            @can('products.edit')
                <form method="POST" action="{{ route('products.toggle', $product) }}">
                    @csrf
                    @method('PATCH')
                    <x-ui.button type="submit" variant="secondary" icon="power">{{ $product->is_active ? __('Deactivate') : __('Activate') }}</x-ui.button>
                </form>
                <x-ui.button :href="route('products.edit', $product)" icon="pencil">{{ __('Edit') }}</x-ui.button>
            @endcan
        </x-slot:actions>
    </x-ui.page-header>

    <div class="grid grid-cols-1 gap-6 xl:grid-cols-3">
        <div class="space-y-6 xl:col-span-2">
            {{-- Overview --}}
            <x-ui.card>
                <div class="flex flex-col gap-6 sm:flex-row">
                    <x-ui.thumbnail :src="$product->image_url" :alt="$product->name" :emoji="$product->placeholder_icon" :color="$product->placeholder_color" icon="products" size="xl" class="sm:w-40" />
                    <div class="min-w-0 flex-1">
                        <div class="flex flex-wrap gap-2">
                            <x-ui.status-badge :active="$product->is_active" />
                            <x-ui.badge :color="$hasVariants ? 'info' : 'gray'">{{ $hasVariants ? __('Variant product') : __('Simple product') }}</x-ui.badge>
                            @if ($product->category)
                                <x-ui.badge color="primary">{{ $product->category->name }}</x-ui.badge>
                            @endif
                            @if ($product->brand)
                                <x-ui.badge>{{ $product->brand->name }}</x-ui.badge>
                            @endif
                        </div>

                        <dl class="mt-5 grid grid-cols-2 gap-x-6 gap-y-4 text-sm sm:grid-cols-3">
                            <div>
                                <dt class="text-gray-500">{{ $hasVariants ? __('Default price') : __('Selling price') }}</dt>
                                <dd class="mt-0.5 font-semibold tabular-nums text-gray-900">
                                    {{ Money::format($finalPrice) }}
                                    @if ($product->discount_type)
                                        <span class="ms-1 text-xs font-normal text-gray-400 line-through">{{ Money::format($product->selling_price) }}</span>
                                    @endif
                                </dd>
                            </div>
                            <div>
                                <dt class="text-gray-500">{{ __('Cost price') }}</dt>
                                <dd class="mt-0.5 tabular-nums text-gray-900">{{ Money::format($product->purchase_price) }}</dd>
                            </div>
                            <div>
                                <dt class="text-gray-500">{{ __('Margin') }}</dt>
                                <dd class="mt-0.5 tabular-nums text-gray-900">{{ $margin !== null ? number_format($margin, 1).'%' : '—' }}</dd>
                            </div>
                            <div>
                                <dt class="text-gray-500">{{ __('Tax rate') }}</dt>
                                <dd class="mt-0.5 tabular-nums text-gray-900">{{ rtrim(rtrim(number_format((float) $product->tax_rate, 2), '0'), '.') }}%</dd>
                            </div>
                            <div>
                                <dt class="text-gray-500">{{ __('Discount') }}</dt>
                                <dd class="mt-0.5 text-gray-900">
                                    @if ($product->discount_type?->value === 'percent')
                                        {{ rtrim(rtrim(number_format((float) $product->discount_value, 2), '0'), '.') }}%
                                    @elseif ($product->discount_type)
                                        {{ Money::format($product->discount_value) }}
                                    @else
                                        —
                                    @endif
                                </dd>
                            </div>
                            <div>
                                <dt class="text-gray-500">{{ __('Barcode') }}</dt>
                                <dd class="mt-0.5 break-all text-gray-900">{{ $product->barcode ?? '—' }}</dd>
                            </div>
                        </dl>

                        @if ($product->description)
                            <p class="mt-5 border-t border-gray-100 pt-4 text-sm leading-6 text-gray-600">{{ $product->description }}</p>
                        @endif
                    </div>
                </div>
            </x-ui.card>

            {{-- Variants --}}
            <x-ui.card :title="__('Variants')" :description="$hasVariants ? __('Blank prices use the product default.') : __('Sizes or options sold as separate items, e.g. Small / Large.')" :padding="false">
                @can('products.edit')
                    <x-slot:actions>
                        <x-ui.button :href="route('products.variants.create', $product)" variant="secondary" size="sm" icon="plus">{{ __('Add variant') }}</x-ui.button>
                    </x-slot:actions>
                @endcan

                @if ($hasVariants)
                    <x-ui.table>
                        <x-slot:head>
                            <th class="px-4 py-3">{{ __('Variant') }}</th>
                            <th class="px-4 py-3 text-right">{{ __('Price') }}</th>
                            <th class="hidden px-4 py-3 text-right sm:table-cell">{{ __('Cost') }}</th>
                            <th class="px-4 py-3"><span class="sr-only">{{ __('Actions') }}</span></th>
                        </x-slot:head>

                        @foreach ($product->variants as $variant)
                            {{-- Avoid lazy-loading the parent for each variant. --}}
                            @php($variant->setRelation('product', $product))
                            <tr class="hover:bg-gray-50">
                                <td class="px-4 py-3">
                                    <p class="font-medium text-gray-900">
                                        {{ $variant->name }}
                                        @unless ($variant->is_active) <x-ui.badge size="sm">{{ __('Inactive') }}</x-ui.badge> @endunless
                                    </p>
                                    <p class="text-xs text-gray-500">{{ $variant->sku }} @if ($variant->barcode) · {{ $variant->barcode }} @endif</p>
                                </td>
                                <td class="whitespace-nowrap px-4 py-3 text-right tabular-nums">
                                    <span class="text-gray-900">{{ Money::format($variant->effectiveSellingPrice()) }}</span>
                                    @if ($variant->selling_price === null)
                                        <p class="text-xs text-gray-400">{{ __('default') }}</p>
                                    @endif
                                </td>
                                <td class="hidden whitespace-nowrap px-4 py-3 text-right tabular-nums text-gray-600 sm:table-cell">
                                    {{ Money::format($variant->effectivePurchasePrice()) }}
                                </td>
                                <td class="px-4 py-3">
                                    @can('products.edit')
                                        <div class="flex items-center justify-end gap-1">
                                            <x-ui.icon-button :href="route('products.variants.edit', [$product, $variant])" icon="pencil" :label="__('Edit')" />
                                            <x-ui.icon-button
                                                icon="trash"
                                                variant="danger"
                                                :label="__('Delete')"
                                                :confirm="[
                                                    'action' => route('products.variants.destroy', [$product, $variant]),
                                                    'title' => __('Delete variant :name?', ['name' => $variant->name]),
                                                    'message' => __('It will no longer be available for sale.'),
                                                ]"
                                            />
                                        </div>
                                    @endcan
                                </td>
                            </tr>
                        @endforeach
                    </x-ui.table>
                @else
                    <x-ui.empty-state icon="products" :title="__('No variants')" :description="__('This product is sold as a single item.')" />
                @endif
            </x-ui.card>

            {{-- Modifier groups --}}
            <x-ui.card :title="__('Modifiers')" :description="__('Add-ons the cashier can select with this product.')">
                @can('products.edit')
                    <x-slot:actions>
                        <x-ui.button :href="route('products.edit', $product)" variant="ghost" size="sm">{{ __('Change') }}</x-ui.button>
                    </x-slot:actions>
                @endcan

                @forelse ($product->modifierGroups as $group)
                    <div @class(['border-t border-gray-100 pt-4 mt-4' => ! $loop->first])>
                        <div class="flex flex-wrap items-center gap-2">
                            <p class="text-sm font-semibold text-gray-900">{{ $group->name }}</p>
                            <x-ui.badge size="sm" :color="$group->isRequired() ? 'warning' : 'gray'">{{ $group->selectionRule() }}</x-ui.badge>
                            @unless ($group->is_active) <x-ui.badge size="sm">{{ __('Inactive') }}</x-ui.badge> @endunless
                        </div>
                        <ul class="mt-2 flex flex-wrap gap-2">
                            @foreach ($group->modifiers as $modifier)
                                <li @class(['rounded-md border px-2.5 py-1 text-xs', 'border-gray-200 text-gray-700' => $modifier->is_active, 'border-dashed border-gray-200 text-gray-400 line-through' => ! $modifier->is_active])>
                                    {{ $modifier->name }}
                                    <span class="tabular-nums text-gray-500">+{{ Money::format($modifier->price) }}</span>
                                </li>
                            @endforeach
                        </ul>
                    </div>
                @empty
                    <p class="text-sm text-gray-500">{{ __('No modifier groups attached.') }}</p>
                @endforelse
            </x-ui.card>
        </div>

        <div class="space-y-6">
            <x-ui.card :title="__('Product type')">
                <p class="text-base font-semibold text-gray-900">{{ $hasVariants ? __('Variant product') : __('Simple product') }}</p>
                <p class="mt-1 text-sm text-gray-500">
                    {{ $hasVariants
                        ? trans_choice('Sold as :count option.|Sold as :count options.', $product->variants->count())
                        : __('Sold as a single item.') }}
                </p>
            </x-ui.card>

            <x-ui.card :title="__('Record')">
                <dl class="space-y-2 text-sm">
                    <div class="flex justify-between"><dt class="text-gray-500">{{ __('Created') }}</dt><dd class="text-gray-900">{{ $product->created_at->isoFormat('D MMM YYYY') }}</dd></div>
                    <div class="flex justify-between"><dt class="text-gray-500">{{ __('Updated') }}</dt><dd class="text-gray-900">{{ $product->updated_at->diffForHumans() }}</dd></div>
                </dl>
                @can('products.delete')
                    <x-ui.button
                        variant="ghost"
                        icon="trash"
                        class="mt-4 w-full text-red-600 hover:bg-red-50"
                        :confirm="[
                            'action' => route('products.destroy', $product),
                            'title' => __('Delete :name?', ['name' => $product->name]),
                            'message' => __('The product is archived and disappears from lists and the POS.'),
                        ]"
                    >{{ __('Delete product') }}</x-ui.button>
                @endcan
            </x-ui.card>
        </div>
    </div>
</x-app-layout>
