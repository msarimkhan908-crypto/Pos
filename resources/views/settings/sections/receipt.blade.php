@php
    use App\Support\FontRegistry;
    $r = settings()->receipt();
    $val = fn (string $key) => old($key, $r[$key]);
    $alignments = ['left' => __('Left'), 'center' => __('Centre'), 'right' => __('Right')];
    $visibility = [
        'show_logo' => __('Logo / header image'),
        'show_address' => __('Business address'),
        'show_phone' => __('Phone'),
        'show_email' => __('Email'),
        'show_website' => __('Website'),
        'show_tax_number' => __('Tax number'),
        'show_customer' => __('Customer'),
        'show_cashier' => __('Cashier'),
        'show_order_type' => __('Order type & table'),
        'show_discount' => __('Discounts'),
        'show_tax' => __('Tax'),
        'show_payment' => __('Payment details'),
        'show_item_notes' => __('Item notes'),
        'show_footer' => __('Footer'),
        'show_separators' => __('Separators'),
        'show_borders' => __('Border'),
    ];
@endphp

<x-ui.card :title="__('Paper & text')">
    <div class="grid gap-5 sm:grid-cols-3">
        <x-ui.select name="width" :label="__('Receipt width')" :options="['58mm' => '58 mm', '80mm' => '80 mm', 'a4' => 'A4']" :selected="$val('width')" />
        <x-ui.select name="font_family" :label="__('Font family')" :options="FontRegistry::options()" :selected="$val('font_family')" />
        <x-ui.select name="heading_font" :label="__('Heading font')" :options="['' => __('Same as font family'), ...FontRegistry::options()]" :selected="$val('heading_font')" />
        <x-ui.select name="font_size" :label="__('Font size')" :options="['sm' => __('Small'), 'md' => __('Medium'), 'lg' => __('Large')]" :selected="$val('font_size')" />
        <x-ui.select name="font_weight" :label="__('Text boldness')" :options="['normal' => __('Normal'), 'medium' => __('Medium'), 'semibold' => __('Semi Bold'), 'bold' => __('Bold'), 'extrabold' => __('Extra Bold'), 'black' => __('Black')]" :selected="$val('font_weight')" />
        <x-ui.select name="item_layout" :label="__('Item layout')" :options="['detailed' => __('Detailed (qty × price)'), 'compact' => __('Compact')]" :selected="$val('item_layout')" />
        <x-ui.select name="header_align" :label="__('Header alignment')" :options="$alignments" :selected="$val('header_align')" />
        <x-ui.select name="footer_align" :label="__('Footer alignment')" :options="$alignments" :selected="$val('footer_align')" />
        <div x-data="{ logoSize: '{{ $val('logo_size') }}' }" class="space-y-2">
            <x-ui.select name="logo_size" :label="__('Logo size')" :options="['sm' => __('Small'), 'md' => __('Medium'), 'lg' => __('Large'), 'custom' => __('Custom')]" :selected="$val('logo_size')" x-on:change="logoSize = $event.target.value" />
            <div x-show="logoSize === 'custom'" x-cloak>
                <x-ui.input type="number" name="logo_custom_height" :label="__('Height (px)')" :value="old('logo_custom_height', $r['logo_custom_height'] ?: '60')" min="20" max="300" :hint="__('20–300 px')" />
            </div>
        </div>
    </div>
</x-ui.card>

{{-- Stop ALL change/input events from the image picker reaching the live-preview form listener --}}
<div x-on:change.stop x-on:input.stop data-no-preview>
<x-ui.card :title="__('Header image')" :description="__('Optional image printed at the top of the receipt instead of the business logo. Any image format (JPG, PNG, WEBP, BMP…). Black-and-white images print best on thermal printers.')">
    <x-ui.image-upload name="header_image" remove-name="remove_header_image" :current="\App\Support\ImageStorage::url(settings('receipt.header_image'))" />
</x-ui.card>
</div>

<x-ui.card :title="__('Text')">
    <div class="space-y-5">
        <x-ui.textarea name="header_text" :label="__('Header text')" :value="$val('header_text')" rows="2" maxlength="200" :hint="__('Printed under the business name, e.g. a slogan.')" />
        <x-ui.textarea name="footer_text" :label="__('Footer')" :value="$val('footer_text')" rows="2" maxlength="300" />
        <x-ui.textarea name="notes" :label="__('Notes')" :value="$val('notes')" rows="2" maxlength="500" :hint="__('e.g. return policy. Printed above the footer.')" />
    </div>
</x-ui.card>

<x-ui.card :title="__('Show on the receipt')">
    <div class="grid gap-3 sm:grid-cols-2 lg:grid-cols-3">
        @foreach ($visibility as $key => $label)
            <x-ui.checkbox :name="$key" :label="$label" :checked="(bool) $val($key)" />
        @endforeach
    </div>
    <p class="mt-3 text-xs text-gray-500">{{ __('Receipt number, date, time, items, quantities, prices and the total are always printed.') }}</p>
</x-ui.card>

{{-- Desktop-only: printer selection. Hidden on web; shown via JS when window.desktop is available. --}}
<div id="desktop-printer-card" class="hidden">
    <x-ui.card
        :title="__('Printer Setup')"
        :description="__('Select which printer to use for the customer receipt and the kitchen order ticket. Only available on the desktop app.')"
    >
        <div class="grid gap-5 sm:grid-cols-2" x-data="printerSetup()">
            <div>
                <label class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-300">{{ __('Receipt Printer') }}</label>
                <select
                    name="desktop_printer"
                    class="block w-full rounded-lg border border-gray-300 text-sm shadow-sm focus:border-primary-500 focus:ring-primary-500 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-100"
                >
                    <option value="">{{ __('System default') }}</option>
                    <template x-for="p in printers" :key="p.name">
                        <option :value="p.name" :selected="p.name === savedReceiptPrinter" x-text="p.name + (p.isDefault ? ' ★' : '')"></option>
                    </template>
                </select>
                <p class="mt-1 text-xs text-gray-500">{{ __('Prints the customer bill after each sale.') }}</p>
            </div>
            <div>
                <label class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-300">{{ __('KOT Printer') }}</label>
                <select
                    name="kot_printer"
                    class="block w-full rounded-lg border border-gray-300 text-sm shadow-sm focus:border-primary-500 focus:ring-primary-500 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-100"
                >
                    <option value="">{{ __('System default') }}</option>
                    <template x-for="p in printers" :key="p.name">
                        <option :value="p.name" :selected="p.name === savedKotPrinter" x-text="p.name + (p.isDefault ? ' ★' : '')"></option>
                    </template>
                </select>
                <p class="mt-1 text-xs text-gray-500">{{ __('Prints the kitchen order ticket. Can be a different printer.') }}</p>
            </div>
        </div>
    </x-ui.card>
</div>

@push('scripts')
<script>
function printerSetup() {
    return {
        printers: [],
        savedReceiptPrinter: @js(settings('receipt.desktop_printer') ?? ''),
        savedKotPrinter: @js(settings('receipt.kot_printer') ?? ''),
        async init() {
            if (window.desktop?.getPrinters) {
                this.printers = await window.desktop.getPrinters();
                document.getElementById('desktop-printer-card')?.classList.remove('hidden');
            }
        },
    };
}
</script>
@endpush
