@use('App\Settings\SettingDefinitions')

@php
    $g = settings()->group('general');
    $sample = \Carbon\CarbonImmutable::parse('2026-09-24 15:30');
    $dateOptions = collect(SettingDefinitions::DATE_FORMATS)->mapWithKeys(fn ($f) => [$f => $sample->format($f)])->all();
    $timeOptions = collect(SettingDefinitions::TIME_FORMATS)->mapWithKeys(fn ($f) => [$f => $sample->format($f)])->all();
    $timezones = collect(timezone_identifiers_list())->mapWithKeys(fn ($tz) => [$tz => str_replace('_', ' ', $tz)])->all();
@endphp

<x-ui.card :title="__('Currency')">
    <div class="grid gap-5 sm:grid-cols-2">
        <x-ui.input name="currency" :label="__('Currency code')" :value="old('currency', $g['general.currency'])" required maxlength="3" :hint="__('3 letters, e.g. PKR, USD, AED')" style="text-transform: uppercase" />
        <x-ui.input name="currency_symbol" :label="__('Currency symbol')" :value="old('currency_symbol', $g['general.currency_symbol'])" maxlength="5" :hint="__('e.g. Rs. Leave blank to show the currency code.')" />
    </div>
</x-ui.card>

<x-ui.card :title="__('Date & time')">
    <div class="grid gap-5 sm:grid-cols-3">
        <x-ui.select name="date_format" :label="__('Date format')" :options="$dateOptions" :selected="old('date_format', $g['general.date_format'])" />
        <x-ui.select name="time_format" :label="__('Time format')" :options="$timeOptions" :selected="old('time_format', $g['general.time_format'])" />
        <x-ui.select name="timezone" :label="__('Timezone')" :options="$timezones" :selected="old('timezone', $g['general.timezone'])" :hint="__('Sets \'today\' for reports and receipt times.')" />
    </div>
</x-ui.card>

<x-ui.card :title="__('Business hours')" :description="__('The time each new business day starts. Sales before this time belong to the previous day\'s report.')">
    <div class="grid gap-5 sm:grid-cols-3">
        <x-ui.input
            name="business_day_start"
            :label="__('Business day starts at')"
            :value="old('business_day_start', $g['general.business_day_start'])"
            type="time"
            :hint="__('HH:MM (24-hour). Default: 00:00.')"
            required
        />
    </div>
</x-ui.card>

<x-ui.card :title="__('Number prefixes')" :description="__('Used for new documents only; existing numbers keep their prefix. The invoice number is also the sale number.')">
    <div class="grid gap-5 sm:grid-cols-3 lg:grid-cols-5">
        <x-ui.input name="invoice_prefix" :label="__('Invoice / sale')" :value="old('invoice_prefix', $g['general.invoice_prefix'])" required maxlength="10" />
        <x-ui.input name="purchase_prefix" :label="__('Purchase')" :value="old('purchase_prefix', $g['general.purchase_prefix'])" required maxlength="10" />
        <x-ui.input name="return_prefix" :label="__('Return')" :value="old('return_prefix', $g['general.return_prefix'])" required maxlength="10" />
        <x-ui.input name="expense_prefix" :label="__('Expense')" :value="old('expense_prefix', $g['general.expense_prefix'])" required maxlength="10" />
        <x-ui.input name="kot_prefix" :label="__('Kitchen ticket')" :value="old('kot_prefix', $g['general.kot_prefix'])" required maxlength="10" />
    </div>
</x-ui.card>

<x-ui.card :title="__('Amount formatting')">
    <x-ui.checkbox name="hide_trailing_zeros" :label="__('Hide .00 on whole amounts')" :checked="(bool) old('hide_trailing_zeros', $g['general.hide_trailing_zeros'])" :description="__('Whole amounts show without decimals (350 not 350.00). Fractional amounts always use 2 decimals (350.50).')" />
</x-ui.card>
