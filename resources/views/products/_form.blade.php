@use('App\Enums\DiscountType')
@use('App\Http\Requests\ProductRequest')

@php
    /** @var \App\Models\Product $product */
    $selectedGroups = collect(old('modifier_groups', $product->exists ? $product->modifierGroups->pluck('id')->all() : []))
        ->map(fn ($id) => (int) $id)
        ->all();
    $categoryOptions = $categories->mapWithKeys(fn ($c) => [$c->id => $c->name.($c->is_active ? '' : ' ('.__('inactive').')')]);
    $brandOptions = $brands->mapWithKeys(fn ($b) => [$b->id => $b->name.($b->is_active ? '' : ' ('.__('inactive').')')]);

    $existingVariants = $product->exists ? $product->variants : collect();
    $hadVariants = $existingVariants->isNotEmpty();
    $productType = old('product_type', $hadVariants ? ProductRequest::VARIABLE : ProductRequest::SIMPLE);

    $variantRows = collect(old('variants', $existingVariants->map(fn ($v) => [
        'id' => $v->id,
        'name' => $v->name,
        'sku' => $v->sku,
        'barcode' => $v->barcode,
        'selling_price' => $v->selling_price,
        'purchase_price' => $v->purchase_price,
        'is_active' => $v->is_active,
    ])->all()))->map(fn ($row) => [
        'id' => filled($row['id'] ?? null) ? (int) $row['id'] : null,
        'name' => $row['name'] ?? '',
        'sku' => $row['sku'] ?? '',
        'barcode' => $row['barcode'] ?? '',
        'selling_price' => $row['selling_price'] ?? '',
        'purchase_price' => $row['purchase_price'] ?? '',
        'is_active' => filter_var($row['is_active'] ?? true, FILTER_VALIDATE_BOOLEAN),
    ])->values();

    $variantErrors = collect($errors->getMessages())
        ->filter(fn ($messages, $key) => str_starts_with($key, 'variants.'))
        ->map(fn ($messages) => $messages[0]);

    $existingDealItems = $product->exists && $product->relationLoaded('dealItems')
        ? $product->dealItems->map(fn ($d) => [
            'component_id' => $d->component_id,
            'component_name' => $d->component?->name ?? '',
            'variant_id' => $d->variant_id,
            'quantity' => $d->quantity,
            'sort_order' => $d->sort_order,
        ])->values()->all()
        : [];

    $dealItemRows = collect(old('deal_items', $existingDealItems))->map(fn ($row) => [
        'component_id' => (int) ($row['component_id'] ?? 0),
        'component_name' => $row['component_name'] ?? '',
        'variant_id' => filled($row['variant_id'] ?? null) ? (int) $row['variant_id'] : null,
        'quantity' => (int) ($row['quantity'] ?? 1),
        'sort_order' => (int) ($row['sort_order'] ?? 0),
    ])->values();

    $isDeal = old('is_deal', $product->is_deal ?? false);

    $dealableProducts = $dealableProducts->mapWithKeys(fn ($p) => [$p->id => $p->name]);

    $cellInput = 'block w-full rounded-lg border-gray-300 text-sm shadow-sm focus:border-primary-500 focus:ring-primary-500 text-gray-900 dark:bg-gray-700 dark:border-gray-600 dark:text-gray-100';
@endphp

<form
    method="POST"
    action="{{ $product->exists ? route('products.update', $product) : route('products.store') }}"
    enctype="multipart/form-data"
    class="grid grid-cols-1 gap-6 lg:grid-cols-3"
    x-data="{
        type: @js($productType),
        discountType: @js(old('discount_type', $product->discount_type?->value)),
        basePrice: @js((string) old('selling_price', $product->selling_price ?? '')),
        baseCost: @js((string) old('purchase_price', $product->purchase_price ?? '')),
        variants: @js($variantRows),
        errors: @js($variantErrors),
        isDeal: @js((bool) $isDeal),
        dealItems: @js($dealItemRows),
        dealableProducts: @js($dealableProducts),
        addVariant() {
            this.variants.push({ id: null, name: '', sku: '', barcode: '', selling_price: '', purchase_price: '', is_active: true });
        },
        removeVariant(index) { this.variants.splice(index, 1); },
        error(index, field) { return this.errors[`variants.${index}.${field}`] ?? null; },
        addDealItem() {
            this.dealItems.push({ component_id: 0, component_name: '', variant_id: null, quantity: 1, sort_order: this.dealItems.length });
        },
        removeDealItem(index) { this.dealItems.splice(index, 1); },
        init() {
            this.$watch('type', (value) => {
                if (value === 'variable' && this.variants.length === 0) {
                    this.addVariant();
                }
            });
            this.$watch('isDeal', (value) => {
                if (value && this.dealItems.length === 0) {
                    this.addDealItem();
                }
            });
        },
    }"
>
    @csrf
    @if ($product->exists)
        @method('PUT')
    @endif

    <div class="space-y-6 lg:col-span-2">
        {{-- Product type --}}
        <x-ui.card :title="__('Product type')">
            <fieldset>
                <legend class="sr-only">{{ __('Product type') }}</legend>
                <div class="grid grid-cols-1 gap-3 sm:grid-cols-2">
                    <label class="relative flex cursor-pointer gap-3 rounded-lg border border-gray-200 p-4 transition hover:border-gray-300 has-[:checked]:border-primary-500 has-[:checked]:bg-primary-50 has-[:checked]:ring-1 has-[:checked]:ring-primary-500 dark:border-gray-700 dark:hover:border-gray-600 dark:has-[:checked]:bg-primary-950">
                        <input type="radio" name="product_type" value="{{ ProductRequest::SIMPLE }}" x-model="type" class="mt-0.5 size-4 border-gray-300 text-primary-600 focus:ring-primary-500 dark:border-gray-600">
                        <span>
                            <span class="block text-sm font-semibold text-gray-900">{{ __('Simple product') }}</span>
                            <span class="mt-0.5 block text-sm text-gray-500">{{ __('Sold as one item with a single price, SKU and barcode.') }}</span>
                        </span>
                    </label>
                    <label class="relative flex cursor-pointer gap-3 rounded-lg border border-gray-200 p-4 transition hover:border-gray-300 has-[:checked]:border-primary-500 has-[:checked]:bg-primary-50 has-[:checked]:ring-1 has-[:checked]:ring-primary-500 dark:border-gray-700 dark:hover:border-gray-600 dark:has-[:checked]:bg-primary-950">
                        <input type="radio" name="product_type" value="{{ ProductRequest::VARIABLE }}" x-model="type" class="mt-0.5 size-4 border-gray-300 text-primary-600 focus:ring-primary-500 dark:border-gray-600">
                        <span>
                            <span class="block text-sm font-semibold text-gray-900">{{ __('Variant product') }}</span>
                            <span class="mt-0.5 block text-sm text-gray-500">{{ __('Sold in options like Small / Medium / Large, each with its own price, SKU and barcode.') }}</span>
                        </span>
                    </label>
                </div>
                @error('product_type')
                    <p class="mt-2 text-sm text-red-600">{{ $message }}</p>
                @enderror
                @if ($hadVariants)
                    <x-ui.alert type="warning" class="mt-4" x-show="type === 'simple'" x-cloak>
                        {{ __('Saving as a simple product removes all variants.') }}
                    </x-ui.alert>
                @endif
            </fieldset>
        </x-ui.card>

        <x-ui.card :title="__('Basic information')">
            <div class="space-y-5">
                <x-ui.input name="name" :label="__('Product name')" :value="$product->name" required maxlength="150" autofocus />
                <div class="grid grid-cols-1 gap-5 sm:grid-cols-2">
                    <x-ui.input name="sku" :label="__('SKU')" :value="$product->sku" maxlength="64" :hint="$product->exists ? __('Unique stock-keeping code.') : __('Leave blank to generate one.')" />
                    <x-ui.input name="barcode" :label="__('Barcode')" :value="$product->barcode" maxlength="64" :hint="__('Optional. Must be unique if set.')" />
                </div>
                <x-ui.textarea name="description" :label="__('Description')" :value="$product->description" rows="4" maxlength="2000" />
            </div>
        </x-ui.card>

        <x-ui.card :title="__('Pricing')">
            <p class="-mt-1 mb-5 text-sm text-gray-500" x-show="type === 'variable'" x-cloak>{{ __('Default prices. Variants without their own price use these.') }}</p>
            <div class="grid grid-cols-1 gap-5 sm:grid-cols-2">
                <x-ui.input name="selling_price" type="number" step="0.01" min="0" :label="__('Selling price')" :value="$product->selling_price" x-model="basePrice" required />
                <x-ui.input name="purchase_price" type="number" step="0.01" min="0" :label="__('Purchase (cost) price')" :value="$product->purchase_price" x-model="baseCost" :hint="__('Used for margins.')" />
                <x-ui.input name="tax_rate" type="number" step="0.01" min="0" max="100" :label="__('Tax rate (%)')" :value="$product->tax_rate ?? 0" />
                <div class="grid grid-cols-2 gap-3">
                    <x-ui.select
                        name="discount_type"
                        :label="__('Discount')"
                        :options="collect(DiscountType::cases())->mapWithKeys(fn ($t) => [$t->value => $t->label()])"
                        :selected="$product->discount_type?->value"
                        :placeholder="__('None')"
                        x-model="discountType"
                    />
                    <div x-show="discountType" x-cloak>
                        <x-ui.input name="discount_value" type="number" step="0.01" min="0" :label="__('Value')" :value="$product->discount_value ?: null" />
                    </div>
                </div>
            </div>
        </x-ui.card>

        {{-- Deal components (deal products only) --}}
        <template x-if="isDeal">
            <x-ui.card :title="__('Deal Components')" :description="__('Products included in this deal at the fixed deal price above.')" :padding="false">
                <x-slot:actions>
                    <x-ui.button variant="secondary" size="sm" icon="plus" x-on:click="addDealItem()">{{ __('Add component') }}</x-ui.button>
                </x-slot:actions>

                @error('deal_items')
                    <x-ui.alert type="danger" class="m-5 mb-0">{{ $message }}</x-ui.alert>
                @enderror

                <div class="divide-y divide-gray-100">
                    <template x-for="(row, index) in dealItems" :key="index">
                        <div class="flex items-center gap-3 px-5 py-3">
                            <div class="flex-1 min-w-0">
                                <label class="mb-1 block text-xs font-medium text-gray-600" x-bind:for="`deal-component-${index}`">{{ __('Product') }} <span class="text-red-500">*</span></label>
                                <select
                                    x-bind:id="`deal-component-${index}`"
                                    x-bind:name="`deal_items[${index}][component_id]`"
                                    x-model="row.component_id"
                                    class="{{ $cellInput }}"
                                >
                                    <option value="0">{{ __('Select a product…') }}</option>
                                    @foreach ($dealableProducts as $id => $name)
                                        <option value="{{ $id }}">{{ $name }}</option>
                                    @endforeach
                                </select>
                            </div>
                            <div class="w-24 shrink-0">
                                <label class="mb-1 block text-xs font-medium text-gray-600" x-bind:for="`deal-qty-${index}`">{{ __('Qty') }}</label>
                                <input
                                    type="number"
                                    min="1"
                                    x-bind:id="`deal-qty-${index}`"
                                    x-bind:name="`deal_items[${index}][quantity]`"
                                    x-model="row.quantity"
                                    class="{{ $cellInput }} tabular-nums"
                                >
                            </div>
                            <input type="hidden" x-bind:name="`deal_items[${index}][sort_order]`" x-bind:value="index">
                            <div class="flex items-end pb-1 shrink-0">
                                <button
                                    type="button"
                                    x-on:click="removeDealItem(index)"
                                    x-bind:disabled="dealItems.length === 1"
                                    title="{{ __('Remove component') }}"
                                    class="inline-flex size-9 items-center justify-center rounded-lg text-gray-400 transition hover:bg-red-50 hover:text-red-600 disabled:cursor-not-allowed disabled:opacity-40"
                                >
                                    <span class="sr-only">{{ __('Remove component') }}</span>
                                    <x-icon name="trash" class="size-4" />
                                </button>
                            </div>
                        </div>
                    </template>
                </div>
            </x-ui.card>
        </template>

        {{-- Variants (variant products only) --}}
        <template x-if="type === 'variable'">
            <x-ui.card :title="__('Variants')" :description="__('Each variant is sold separately. Blank price or cost uses the product default.')" :padding="false">
                <x-slot:actions>
                    <x-ui.button variant="secondary" size="sm" icon="plus" x-on:click="addVariant()">{{ __('Add variant') }}</x-ui.button>
                </x-slot:actions>

                @error('variants')
                    <x-ui.alert type="danger" class="m-5 mb-0">{{ $message }}</x-ui.alert>
                @enderror

                <div class="divide-y divide-gray-100">
                    <template x-for="(row, index) in variants" :key="index">
                        <div class="space-y-3 px-5 py-4">
                            <input type="hidden" x-bind:name="`variants[${index}][id]`" x-bind:value="row.id ?? ''">

                            <div class="grid grid-cols-1 gap-3 sm:grid-cols-3">
                                <div>
                                    <label class="mb-1 block text-xs font-medium text-gray-600" x-bind:for="`variant-name-${index}`">{{ __('Name') }} <span class="text-red-500">*</span></label>
                                    <input type="text" x-bind:id="`variant-name-${index}`" x-bind:name="`variants[${index}][name]`" x-model="row.name" required maxlength="100" placeholder="{{ __('e.g. Large') }}" class="{{ $cellInput }}" x-bind:class="error(index, 'name') && 'border-red-300'">
                                    <p class="mt-1 text-xs text-red-600" x-show="error(index, 'name')" x-text="error(index, 'name')"></p>
                                </div>
                                <div>
                                    <label class="mb-1 block text-xs font-medium text-gray-600" x-bind:for="`variant-sku-${index}`">{{ __('SKU') }}</label>
                                    <input type="text" x-bind:id="`variant-sku-${index}`" x-bind:name="`variants[${index}][sku]`" x-model="row.sku" maxlength="64" x-bind:placeholder="row.id ? '' : @js(__('Auto'))" class="{{ $cellInput }}" x-bind:class="error(index, 'sku') && 'border-red-300'">
                                    <p class="mt-1 text-xs text-red-600" x-show="error(index, 'sku')" x-text="error(index, 'sku')"></p>
                                </div>
                                <div>
                                    <label class="mb-1 block text-xs font-medium text-gray-600" x-bind:for="`variant-barcode-${index}`">{{ __('Barcode') }}</label>
                                    <input type="text" x-bind:id="`variant-barcode-${index}`" x-bind:name="`variants[${index}][barcode]`" x-model="row.barcode" maxlength="64" class="{{ $cellInput }}" x-bind:class="error(index, 'barcode') && 'border-red-300'">
                                    <p class="mt-1 text-xs text-red-600" x-show="error(index, 'barcode')" x-text="error(index, 'barcode')"></p>
                                </div>
                            </div>

                            <div class="grid grid-cols-2 items-end gap-3 sm:grid-cols-4">
                                <div>
                                    <label class="mb-1 block text-xs font-medium text-gray-600" x-bind:for="`variant-price-${index}`">{{ __('Price') }}</label>
                                    <input type="number" step="0.01" min="0" x-bind:id="`variant-price-${index}`" x-bind:name="`variants[${index}][selling_price]`" x-model="row.selling_price" x-bind:placeholder="basePrice" class="{{ $cellInput }} tabular-nums" x-bind:class="error(index, 'selling_price') && 'border-red-300'">
                                    <p class="mt-1 text-xs text-red-600" x-show="error(index, 'selling_price')" x-text="error(index, 'selling_price')"></p>
                                </div>
                                <div>
                                    <label class="mb-1 block text-xs font-medium text-gray-600" x-bind:for="`variant-cost-${index}`">{{ __('Cost') }}</label>
                                    <input type="number" step="0.01" min="0" x-bind:id="`variant-cost-${index}`" x-bind:name="`variants[${index}][purchase_price]`" x-model="row.purchase_price" x-bind:placeholder="baseCost" class="{{ $cellInput }} tabular-nums" x-bind:class="error(index, 'purchase_price') && 'border-red-300'">
                                    <p class="mt-1 text-xs text-red-600" x-show="error(index, 'purchase_price')" x-text="error(index, 'purchase_price')"></p>
                                </div>
                                <div class="flex items-center pb-2">
                                    <input type="hidden" x-bind:name="`variants[${index}][is_active]`" value="0">
                                    <label class="flex items-center gap-2 text-sm text-gray-600">
                                        <input type="checkbox" value="1" x-bind:name="`variants[${index}][is_active]`" x-model="row.is_active" class="size-4 rounded border-gray-300 text-primary-600 focus:ring-primary-500 dark:border-gray-600">
                                        {{ __('Active') }}
                                    </label>
                                </div>
                                <div class="flex justify-end pb-1">
                                    <button
                                        type="button"
                                        x-on:click="removeVariant(index)"
                                        x-bind:disabled="variants.length === 1"
                                        title="{{ __('Remove variant') }}"
                                        class="inline-flex size-9 items-center justify-center rounded-lg text-gray-400 transition hover:bg-red-50 hover:text-red-600 disabled:cursor-not-allowed disabled:opacity-40"
                                    >
                                        <span class="sr-only">{{ __('Remove variant') }}</span>
                                        <x-icon name="trash" class="size-4" />
                                    </button>
                                </div>
                            </div>
                        </div>
                    </template>
                </div>
            </x-ui.card>
        </template>
    </div>

    <div class="space-y-6">
        <x-ui.card :title="__('Product image')">
            <x-ui.image-upload name="image" :current="$product->image_url" />
        </x-ui.card>

        <x-ui.card :title="__('Organisation')">
            <div class="space-y-5">
                <x-ui.select name="category_id" :label="__('Category')" :options="$categoryOptions" :selected="$product->category_id" :placeholder="__('No category')" />
                <x-ui.select name="brand_id" :label="__('Brand')" :options="$brandOptions" :selected="$product->brand_id" :placeholder="__('No brand')" />
                <x-ui.select name="kitchen_station_id" :label="__('Kitchen station')" :options="\App\Models\KitchenStation::options()" :selected="$product->kitchen_station_id" :placeholder="__('Same as category')" :hint="__('Where this product is prepared. Without a station (here or on the category) it is not sent to the kitchen.')" />
                <x-ui.input name="sort_order" type="number" min="0" step="1" :label="__('Sort order')" :value="old('sort_order', $product->sort_order ?? 0)" :hint="__('Lower numbers appear first on the POS screen.')" />
                <x-ui.checkbox name="is_active" :label="__('Active')" :description="__('Inactive products are hidden from the POS.')" :checked="$product->is_active" />
                <div>
                    <input type="hidden" name="is_deal" value="0">
                    <label class="flex items-start gap-3 text-sm">
                        <input type="checkbox" name="is_deal" value="1" x-model="isDeal" class="mt-0.5 size-4 rounded border-gray-300 text-primary-600 focus:ring-primary-500 dark:border-gray-600">
                        <span>
                            <span class="font-medium text-gray-900">{{ __('Deal / Combo') }}</span>
                            <span class="mt-0.5 block text-gray-500">{{ __('Bundles multiple products sold at a fixed deal price.') }}</span>
                        </span>
                    </label>
                </div>
            </div>
        </x-ui.card>

        <x-ui.card :title="__('Modifier groups')" :description="__('Add-ons offered with this product.')">
            @if ($modifierGroups->isEmpty())
                <p class="text-sm text-gray-500">
                    {{ __('No modifier groups yet.') }}
                    @can('products.create')
                        <a href="{{ route('modifier-groups.create') }}" class="font-medium text-primary-600 hover:text-primary-500">{{ __('Create one') }}</a>
                    @endcan
                </p>
            @else
                <div class="space-y-3">
                    @foreach ($modifierGroups as $group)
                        <label class="flex items-start gap-3 text-sm">
                            <input type="checkbox" name="modifier_groups[]" value="{{ $group->id }}" @checked(in_array($group->id, $selectedGroups, true)) class="mt-0.5 size-4 rounded border-gray-300 text-primary-600 focus:ring-primary-500 dark:border-gray-600">
                            <span>
                                <span class="font-medium text-gray-900">{{ $group->name }}</span>
                                @unless ($group->is_active) <x-ui.badge size="sm">{{ __('Inactive') }}</x-ui.badge> @endunless
                                <span class="block text-gray-500">{{ trans_choice(':count option|:count options', $group->modifiers_count) }} · {{ $group->selectionRule() }}</span>
                            </span>
                        </label>
                    @endforeach
                </div>
                @error('modifier_groups.*')
                    <p class="mt-2 text-sm text-red-600">{{ $message }}</p>
                @enderror
            @endif
        </x-ui.card>
    </div>

    <div class="flex flex-col-reverse gap-2 sm:flex-row sm:justify-end lg:col-span-3">
        <x-ui.button :href="$product->exists ? route('products.show', $product) : route('products.index')" variant="secondary">{{ __('Cancel') }}</x-ui.button>
        <x-ui.button type="submit">{{ $product->exists ? __('Save changes') : __('Create product') }}</x-ui.button>
    </div>
</form>
