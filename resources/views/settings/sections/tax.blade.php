<x-ui.card :title="__('Tax')" :description="__('Each product has its own tax rate; this is the rate new products start with.')">
    <div class="grid gap-5 sm:grid-cols-2">
        <x-ui.input name="default_rate" type="number" step="0.01" min="0" max="100" :label="__('Default tax rate (%)')" :value="old('default_rate', settings('tax.default_rate'))" required />
        <x-ui.input name="label" :label="__('Tax label')" :value="old('label', settings('tax.label'))" required maxlength="20" :hint="__('Shown on receipts and in the POS, e.g. GST, VAT, Sales Tax')" />
    </div>
    <p class="mt-4 text-sm text-gray-500">
        {{ __('The business tax number is set under') }}
        <a href="{{ route('settings.business') }}" class="font-semibold text-primary-700 hover:underline">{{ __('General Settings → Business') }}</a>.
    </p>
</x-ui.card>
