@use('App\Support\Money')

{{-- $product, $variant (new or existing) --}}
<form
    method="POST"
    action="{{ $variant->exists ? route('products.variants.update', [$product, $variant]) : route('products.variants.store', $product) }}"
    class="grid grid-cols-1 gap-6 lg:grid-cols-3"
>
    @csrf
    @if ($variant->exists)
        @method('PUT')
    @endif

    <div class="space-y-6 lg:col-span-2">
        <x-ui.card :title="__('Variant details')">
            <div class="space-y-5">
                <x-ui.input name="name" :label="__('Variant name')" :value="$variant->name" required maxlength="100" :placeholder="__('e.g. Large')" autofocus />
                <div class="grid grid-cols-1 gap-5 sm:grid-cols-2">
                    <x-ui.input name="sku" :label="__('SKU')" :value="$variant->sku" maxlength="64" :hint="$variant->exists ? null : __('Leave blank to derive it from the product SKU.')" />
                    <x-ui.input name="barcode" :label="__('Barcode')" :value="$variant->barcode" maxlength="64" :hint="__('Optional. Unique across all products and variants.')" />
                </div>
            </div>
        </x-ui.card>

        <x-ui.card :title="__('Pricing')" :description="__('Leave blank to use the product price.')">
            <div class="grid grid-cols-1 gap-5 sm:grid-cols-2">
                <x-ui.input name="selling_price" type="number" step="0.01" min="0" :label="__('Selling price')" :value="$variant->selling_price" :placeholder="Money::format($product->selling_price)" />
                <x-ui.input name="purchase_price" type="number" step="0.01" min="0" :label="__('Purchase (cost) price')" :value="$variant->purchase_price" :placeholder="Money::format($product->purchase_price)" />
            </div>
        </x-ui.card>
    </div>

    <div class="space-y-6">
        <x-ui.card :title="__('Settings')">
            <div class="space-y-5">
                <x-ui.checkbox name="is_active" :label="__('Active')" :description="__('Inactive variants are hidden from the POS.')" :checked="$variant->is_active" />
                <x-ui.input name="sort_order" type="number" min="0" max="65535" :label="__('Display order')" :value="$variant->sort_order ?? 0" />
            </div>
        </x-ui.card>
    </div>

    <div class="flex flex-col-reverse gap-2 sm:flex-row sm:justify-end lg:col-span-3">
        <x-ui.button :href="route('products.show', $product)" variant="secondary">{{ __('Cancel') }}</x-ui.button>
        <x-ui.button type="submit">{{ $variant->exists ? __('Save changes') : __('Add variant') }}</x-ui.button>
    </div>
</form>
