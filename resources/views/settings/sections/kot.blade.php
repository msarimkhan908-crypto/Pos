@php
    use App\Settings\SettingDefinitions;
    use App\Support\FontRegistry;
    $k = settings()->kot();
    $val = fn (string $key) => old($key, $k[$key]);
    $visibility = [
        'show_table' => __('Table'),
        'show_order_type' => __('Order type'),
        'show_invoice' => __('Order number'),
        'show_customer' => __('Customer'),
        'show_cashier' => __('Cashier'),
        'show_notes' => __('Notes & special instructions'),
        'show_station_headings' => __('Station headings'),
        'show_logo' => __('Business logo'),
        'show_separators' => __('Separators'),
    ];
@endphp

<x-ui.card :title="__('Paper & text')" :description="__('Kitchen tickets are printed large and simple for busy kitchens.')">
    <div class="grid gap-5 sm:grid-cols-3">
        <x-ui.select name="width" :label="__('Ticket width')" :options="['58mm' => '58 mm', '80mm' => '80 mm']" :selected="$val('width')" />
        <x-ui.select name="font_family" :label="__('Font family')" :options="FontRegistry::options()" :selected="$val('font_family')" />
        <x-ui.select name="font_size" :label="__('Font size')" :options="['sm' => __('Small'), 'md' => __('Medium'), 'lg' => __('Large')]" :selected="$val('font_size')" />
        <x-ui.select name="item_size" :label="__('Item text size')" :options="['normal' => __('Normal'), 'large' => __('Large'), 'extra_large' => __('Extra large')]" :selected="$val('item_size')" />
        <x-ui.select name="align" :label="__('Header alignment')" :options="['left' => __('Left'), 'center' => __('Centre'), 'right' => __('Right')]" :selected="$val('align')" />
    </div>
    <div class="mt-5 grid gap-5 sm:grid-cols-2">
        <x-ui.input name="header_text" :label="__('Header text')" :value="$val('header_text')" maxlength="100" />
        <x-ui.input name="footer_text" :label="__('Footer text')" :value="$val('footer_text')" maxlength="200" />
    </div>
</x-ui.card>

<x-ui.card :title="__('Show on the kitchen ticket')">
    <div class="grid gap-3 sm:grid-cols-2 lg:grid-cols-3">
        @foreach ($visibility as $key => $label)
            <x-ui.checkbox :name="$key" :label="$label" :checked="(bool) $val($key)" />
        @endforeach
    </div>
    <p class="mt-3 text-xs text-gray-500">{{ __('The KOT number, date, time, products, variants, modifiers and quantities are always printed.') }}</p>
</x-ui.card>
