@use('App\Enums\PaymentStatus')

@php
    /** @var \App\Models\Purchase $purchase */
    $initialItems = old('items', $purchase->exists
        ? $purchase->items->map(fn ($item) => [
            'product_id' => $item->product_id,
            'variant_id' => $item->variant_id,
            'quantity' => rtrim(rtrim((string) $item->quantity, '0'), '.'),
            'purchase_price' => (string) $item->purchase_price,
            'discount' => (string) $item->discount,
            'tax' => (string) $item->tax,
        ])->all()
        : []);

    $itemErrors = collect($errors->getMessages())
        ->filter(fn ($messages, $key) => str_starts_with($key, 'items.'))
        ->map(fn ($messages) => $messages[0]);

    $supplierOptions = $suppliers->mapWithKeys(fn ($s) => [$s->id => $s->name.($s->company_name ? ' — '.$s->company_name : '')]);
    $cell = 'block w-full rounded-lg border-gray-300 text-sm shadow-sm focus:border-primary-500 focus:ring-primary-500';
    $currency = \App\Support\Money::currency();
@endphp

<form
    method="POST"
    action="{{ $purchase->exists ? route('purchases.update', $purchase) : route('purchases.store') }}"
    class="space-y-6"
    x-data="purchaseForm(@js(['products' => $products, 'items' => array_values($initialItems), 'errors' => $itemErrors]))"
>
    @csrf
    @if ($purchase->exists)
        @method('PUT')
    @endif

    <div class="grid grid-cols-1 gap-6 lg:grid-cols-3">
        <x-ui.card class="lg:col-span-2" :title="__('Purchase details')">
            <div class="grid grid-cols-1 gap-5 sm:grid-cols-2">
                <div class="sm:col-span-2">
                    <x-ui.select name="supplier_id" :label="__('Supplier')" :options="$supplierOptions" :selected="$purchase->supplier_id" :placeholder="__('Select a supplier…')" required />
                    @if ($suppliers->isEmpty())
                        <p class="mt-1.5 text-sm text-amber-700">
                            {{ __('No active suppliers.') }}
                            @can('suppliers.create')
                                <a href="{{ route('suppliers.create') }}" class="font-medium underline">{{ __('Add a supplier') }}</a>
                            @endcan
                        </p>
                    @endif
                </div>
                <x-ui.input name="purchase_date" type="date" :label="__('Purchase date')" :value="$purchase->purchase_date?->toDateString()" required />
                <x-ui.select name="payment_status" :label="__('Payment status')" :options="PaymentStatus::options()" :selected="$purchase->payment_status?->value ?? PaymentStatus::Unpaid->value" required />
            </div>
        </x-ui.card>

        <x-ui.card :title="__('Notes')">
            <x-ui.textarea name="notes" :value="$purchase->notes" rows="5" maxlength="2000" :aria-label="__('Notes')" :placeholder="__('Invoice number, delivery details…')" />
        </x-ui.card>
    </div>

    {{-- Items --}}
    <x-ui.card :title="__('Items')" :description="__('Products bought on this purchase. Discount and tax are amounts per line.')" :padding="false">
        <x-slot:actions>
            <x-ui.button variant="secondary" size="sm" icon="plus" x-on:click="addRow()">{{ __('Add item') }}</x-ui.button>
        </x-slot:actions>

        @error('items')
            <x-ui.alert type="danger" class="m-5 mb-0">{{ $message }}</x-ui.alert>
        @enderror

        {{-- Column labels (desktop) --}}
        <div class="hidden grid-cols-12 gap-3 border-b border-gray-100 bg-gray-50 px-5 py-2 text-xs font-semibold uppercase tracking-wide text-gray-500 lg:grid">
            <div class="col-span-4">{{ __('Product') }}</div>
            <div class="col-span-1">{{ __('Qty') }}</div>
            <div class="col-span-2">{{ __('Unit cost') }}</div>
            <div class="col-span-1">{{ __('Discount') }}</div>
            <div class="col-span-1">{{ __('Tax') }}</div>
            <div class="col-span-2 text-right">{{ __('Line total') }}</div>
            <div class="col-span-1"></div>
        </div>

        <div class="divide-y divide-gray-100">
            <template x-for="(row, index) in rows" :key="row.key">
                <div class="grid grid-cols-2 gap-3 px-5 py-4 lg:grid-cols-12 lg:items-start">
                    <input type="hidden" x-bind:name="`items[${index}][product_id]`" x-bind:value="row.product_id">

                    {{-- Product picker + variant --}}
                    <div class="col-span-2 space-y-2 lg:col-span-4">
                        <div class="relative" x-on:click.outside="row.open = false">
                            <label class="mb-1 block text-xs font-medium text-gray-600 lg:sr-only" x-bind:for="`item-product-${index}`">{{ __('Product') }}</label>
                            <input
                                type="text"
                                x-bind:id="`item-product-${index}`"
                                x-model="row.query"
                                x-on:focus="row.open = true"
                                x-on:input="row.open = true; row.product_id = ''; row.variant_id = ''"
                                x-on:keydown.enter.prevent="matches(row)[0] && choose(row, matches(row)[0])"
                                x-on:keydown.escape="row.open = false"
                                placeholder="{{ __('Search product, SKU or barcode…') }}"
                                autocomplete="off"
                                class="{{ $cell }}"
                                x-bind:class="(error(index, 'product_id') || (row.query && ! row.product_id)) && 'border-red-300'"
                            >
                            <ul
                                x-show="row.open"
                                x-cloak
                                class="absolute z-20 mt-1 max-h-64 w-full overflow-y-auto rounded-lg border border-gray-200 bg-white py-1 text-sm shadow-lg"
                            >
                                <template x-for="product in matches(row)" :key="product.id">
                                    <li>
                                        <button type="button" x-on:click="choose(row, product)" class="flex w-full items-center justify-between gap-3 px-3 py-2 text-left hover:bg-gray-50">
                                            <span class="min-w-0">
                                                <span class="block truncate font-medium text-gray-900" x-text="product.name"></span>
                                                <span class="block truncate text-xs text-gray-500" x-text="product.sku + (product.variants.length ? ' · ' + product.variants.length + ' {{ __('variants') }}' : '')"></span>
                                            </span>
                                            <span class="shrink-0 text-xs tabular-nums text-gray-500" x-show="! product.variants.length" x-text="product.cost"></span>
                                        </button>
                                    </li>
                                </template>
                                <li x-show="matches(row).length === 0" class="px-3 py-2 text-gray-500">{{ __('No products found.') }}</li>
                            </ul>
                            <p class="mt-1 text-xs text-red-600" x-show="error(index, 'product_id')" x-text="error(index, 'product_id')"></p>
                        </div>

                        <template x-if="variantsOf(row).length">
                            <div>
                                <label class="sr-only" x-bind:for="`item-variant-${index}`">{{ __('Variant') }}</label>
                                <select
                                    x-bind:id="`item-variant-${index}`"
                                    x-bind:name="`items[${index}][variant_id]`"
                                    x-model="row.variant_id"
                                    x-on:change="chooseVariant(row)"
                                    class="{{ $cell }}"
                                    x-bind:class="error(index, 'variant_id') && 'border-red-300'"
                                >
                                    <option value="">{{ __('Select a variant…') }}</option>
                                    <template x-for="variant in variantsOf(row)" :key="variant.id">
                                        <option x-bind:value="variant.id" x-text="variant.name + (variant.active ? '' : ' ({{ __('inactive') }})')" x-bind:selected="Number(row.variant_id) === variant.id"></option>
                                    </template>
                                </select>
                                <p class="mt-1 text-xs text-red-600" x-show="error(index, 'variant_id')" x-text="error(index, 'variant_id')"></p>
                            </div>
                        </template>
                    </div>

                    <div class="lg:col-span-1">
                        <label class="mb-1 block text-xs font-medium text-gray-600 lg:sr-only" x-bind:for="`item-qty-${index}`">{{ __('Quantity') }}</label>
                        <input type="number" step="0.001" min="0.001" x-bind:id="`item-qty-${index}`" x-bind:name="`items[${index}][quantity]`" x-model="row.quantity" required class="{{ $cell }} tabular-nums" x-bind:class="error(index, 'quantity') && 'border-red-300'">
                        <p class="mt-1 text-xs text-red-600" x-show="error(index, 'quantity')" x-text="error(index, 'quantity')"></p>
                    </div>

                    <div class="lg:col-span-2">
                        <label class="mb-1 block text-xs font-medium text-gray-600 lg:sr-only" x-bind:for="`item-price-${index}`">{{ __('Unit cost') }}</label>
                        <input type="number" step="0.01" min="0" x-bind:id="`item-price-${index}`" x-bind:name="`items[${index}][purchase_price]`" x-model="row.purchase_price" required class="{{ $cell }} tabular-nums" x-bind:class="error(index, 'purchase_price') && 'border-red-300'">
                        <p class="mt-1 text-xs text-red-600" x-show="error(index, 'purchase_price')" x-text="error(index, 'purchase_price')"></p>
                    </div>

                    <div class="lg:col-span-1">
                        <label class="mb-1 block text-xs font-medium text-gray-600 lg:sr-only" x-bind:for="`item-discount-${index}`">{{ __('Discount') }}</label>
                        <input type="number" step="0.01" min="0" x-bind:id="`item-discount-${index}`" x-bind:name="`items[${index}][discount]`" x-model="row.discount" class="{{ $cell }} tabular-nums" x-bind:class="error(index, 'discount') && 'border-red-300'">
                        <p class="mt-1 text-xs text-red-600" x-show="error(index, 'discount')" x-text="error(index, 'discount')"></p>
                    </div>

                    <div class="lg:col-span-1">
                        <label class="mb-1 block text-xs font-medium text-gray-600 lg:sr-only" x-bind:for="`item-tax-${index}`">{{ __('Tax') }}</label>
                        <input type="number" step="0.01" min="0" x-bind:id="`item-tax-${index}`" x-bind:name="`items[${index}][tax]`" x-model="row.tax" class="{{ $cell }} tabular-nums" x-bind:class="error(index, 'tax') && 'border-red-300'">
                        <p class="mt-1 text-xs text-red-600" x-show="error(index, 'tax')" x-text="error(index, 'tax')"></p>
                    </div>

                    <div class="flex items-center justify-between gap-2 lg:col-span-2 lg:justify-end lg:pt-2">
                        <span class="text-xs font-medium text-gray-600 lg:hidden">{{ __('Line total') }}</span>
                        <span class="text-sm font-semibold tabular-nums text-gray-900" x-text="lineTotal(row)"></span>
                    </div>

                    <div class="flex justify-end lg:col-span-1">
                        <button type="button" x-on:click="removeRow(index)" class="inline-flex size-9 items-center justify-center rounded-lg text-gray-400 transition hover:bg-red-50 hover:text-red-600" title="{{ __('Remove item') }}">
                            <span class="sr-only">{{ __('Remove item') }}</span>
                            <x-icon name="trash" class="size-4" />
                        </button>
                    </div>
                </div>
            </template>
        </div>

        <x-slot:footer>
            <div class="flex flex-col gap-4 sm:flex-row sm:items-start sm:justify-between">
                <x-ui.button variant="ghost" size="sm" icon="plus" x-on:click="addRow()">{{ __('Add another item') }}</x-ui.button>
                <dl class="w-full space-y-1.5 text-sm sm:w-72">
                    <div class="flex justify-between"><dt class="text-gray-500">{{ __('Subtotal') }}</dt><dd class="tabular-nums text-gray-900" x-text="subtotal"></dd></div>
                    <div class="flex justify-between"><dt class="text-gray-500">{{ __('Discount') }}</dt><dd class="tabular-nums text-gray-900">−<span x-text="discountTotal"></span></dd></div>
                    <div class="flex justify-between"><dt class="text-gray-500">{{ __('Tax') }}</dt><dd class="tabular-nums text-gray-900">+<span x-text="taxTotal"></span></dd></div>
                    <div class="flex justify-between border-t border-gray-200 pt-2 text-base font-semibold"><dt class="text-gray-900">{{ __('Total') }} <span class="text-xs font-normal text-gray-400">{{ $currency }}</span></dt><dd class="tabular-nums text-gray-900" x-text="grandTotal"></dd></div>
                    <p class="pt-1 text-xs text-gray-400">{{ __('Totals are recalculated by the server when you save.') }}</p>
                </dl>
            </div>
        </x-slot:footer>
    </x-ui.card>

    @error('status')
        <x-ui.alert type="danger">{{ $message }}</x-ui.alert>
    @enderror

    <div class="flex flex-col-reverse gap-2 sm:flex-row sm:justify-end">
        <x-ui.button :href="$purchase->exists ? route('purchases.show', $purchase) : route('purchases.index')" variant="secondary">{{ __('Cancel') }}</x-ui.button>
        <x-ui.button type="submit" name="status" value="draft" variant="secondary">{{ __('Save as draft') }}</x-ui.button>
        <x-ui.button type="submit" name="status" value="completed" icon="check-circle">{{ __('Save & complete') }}</x-ui.button>
    </div>
</form>
