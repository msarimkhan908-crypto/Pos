@use('App\Enums\MoneyMethod')
@use('App\Enums\ReturnReason')
@use('App\Http\Controllers\ReturnController')
@use('App\Support\Money')
@use('App\Support\Format')

<x-app-layout>
    <x-slot name="title">{{ __('New Return') }}</x-slot>

    <x-ui.page-header :title="__('New Return')" :description="__('Find the sale, choose what is coming back, and refund it.')">
        <x-slot:breadcrumbs>
            <a href="{{ route('returns.index') }}" class="hover:text-gray-700">{{ __('Returns') }}</a>
        </x-slot:breadcrumbs>
    </x-ui.page-header>

    {{-- Step 1: find the sale --}}
    <x-ui.card class="mb-6" :title="__('1. Find the sale')">
        <form method="GET" action="{{ route('returns.create') }}" class="flex flex-col gap-3 sm:flex-row sm:items-end">
            <div class="flex-1">
                <x-ui.input name="invoice" :label="__('Invoice number')" :value="$invoice ?: $sale?->invoice_number" :placeholder="__('e.g. INV-000123')" autofocus required />
            </div>
            <x-ui.button type="submit" variant="secondary" icon="search">{{ __('Find sale') }}</x-ui.button>
        </form>
        @if ($error)
            <p class="mt-3 rounded-md bg-red-50 px-3 py-2 text-sm text-red-700" role="alert">{{ $error }}</p>
        @endif
    </x-ui.card>

    @if ($sale)
        @php
            $rows = ReturnController::formRows($sale);
            $oldQuantities = collect(old('items', []))->mapWithKeys(fn ($row) => [$row['sale_item_id'] ?? 0 => $row['quantity'] ?? 0])->all();
        @endphp

        <form method="POST" action="{{ route('returns.store') }}"
            x-data="returnForm(@js(['rows' => $rows, 'old' => $oldQuantities, 'currency' => Money::currency(), 'symbol' => (string) settings('general.currency_symbol')]))">
            @csrf
            <input type="hidden" name="sale_id" value="{{ $sale->id }}">

            <x-ui.card class="mb-6" :title="__('2. Choose items — :invoice', ['invoice' => $sale->invoice_number])"
                :description="__(':date · :customer · paid :total', ['date' => Format::dateTime($sale->completed_at), 'customer' => $sale->customerLabel(), 'total' => Money::format($sale->total)])"
                :padding="false">
                @error('items')
                    <p class="mx-4 mt-4 rounded-md bg-red-50 px-3 py-2 text-sm text-red-700" role="alert">{{ $message }}</p>
                @enderror

                <x-ui.table>
                    <x-slot:head>
                        <th class="px-4 py-3">{{ __('Item') }}</th>
                        <th class="px-4 py-3 text-right">{{ __('Sold') }}</th>
                        <th class="px-4 py-3 text-right">{{ __('Returned') }}</th>
                        <th class="px-4 py-3 text-right">{{ __('Paid') }}</th>
                        <th class="px-4 py-3">{{ __('Return qty') }}</th>
                        <th class="hidden px-4 py-3 md:table-cell">{{ __('Item reason') }}</th>
                        <th class="px-4 py-3 text-right">{{ __('Refund') }}</th>
                    </x-slot:head>
                    <template x-for="(row, index) in rows" :key="row.id">
                        <tr x-bind:class="row.returnable === 0 && 'bg-gray-50 text-gray-400'" data-return-row>
                            <td class="px-4 py-3">
                                <input type="hidden" x-bind:name="`items[${index}][sale_item_id]`" x-bind:value="row.id">
                                <p class="font-medium" x-text="row.name"></p>
                                <p class="text-xs text-gray-500" x-show="row.modifiers.length" x-text="'+ ' + row.modifiers.join(', ')"></p>
                                <p class="text-xs font-semibold text-red-600" x-show="row.returnable === 0">{{ __('Fully returned') }}</p>
                            </td>
                            <td class="px-4 py-3 text-right tabular-nums" x-text="row.sold"></td>
                            <td class="px-4 py-3 text-right tabular-nums" x-text="row.returned"></td>
                            <td class="px-4 py-3 text-right tabular-nums" x-text="format(Math.round(parseFloat(row.paid) * 100))"></td>
                            <td class="px-4 py-3">
                                <input type="number" min="0" x-bind:max="row.returnable" step="1" inputmode="numeric"
                                    x-bind:name="`items[${index}][quantity]`" x-model.number="row.quantity" x-bind:disabled="row.returnable === 0"
                                    class="w-20 rounded-lg border-gray-300 text-sm tabular-nums focus:border-primary-500 focus:ring-primary-500 disabled:bg-gray-100 dark:bg-gray-700 dark:border-gray-600 dark:text-gray-100 dark:disabled:bg-gray-800"
                                    x-bind:aria-label="'{{ __('Return quantity for') }} ' + row.name">
                                <span class="ml-1 text-xs text-gray-500" x-text="'/ ' + row.returnable"></span>
                            </td>
                            <td class="hidden px-4 py-3 md:table-cell">
                                <select x-bind:name="`items[${index}][reason]`" x-bind:disabled="row.returnable === 0" class="rounded-lg border-gray-300 text-sm focus:border-primary-500 focus:ring-primary-500 dark:bg-gray-700 dark:border-gray-600 dark:text-gray-100">
                                    <option value="">{{ __('Same as return') }}</option>
                                    @foreach (ReturnReason::options() as $value => $label)
                                        <option value="{{ $value }}">{{ $label }}</option>
                                    @endforeach
                                </select>
                            </td>
                            <td class="px-4 py-3 text-right font-medium tabular-nums" x-text="format(refundCents(row))"></td>
                        </tr>
                    </template>
                </x-ui.table>

                @foreach ($errors->get('items.*') as $messages)
                    <p class="mx-4 mt-2 text-sm text-red-600" role="alert">{{ $messages[0] }}</p>
                @endforeach

                <div class="flex flex-wrap items-center justify-between gap-3 border-t border-gray-100 px-4 py-3">
                    <div class="flex gap-2">
                        <x-ui.button variant="ghost" size="sm" x-on:click="returnAll()">{{ __('Return everything left') }}</x-ui.button>
                        <x-ui.button variant="ghost" size="sm" x-on:click="clearAll()">{{ __('Clear') }}</x-ui.button>
                    </div>
                    <p class="text-sm text-gray-600">{{ __('Estimated refund') }}: <span class="text-lg font-bold tabular-nums text-gray-900" x-text="format(total)" data-refund-total></span></p>
                </div>
            </x-ui.card>

            <x-ui.card :title="__('3. Refund')" :description="__('The refund is calculated from what the customer paid for each item (after discounts, including tax). The delivery fee is not refunded.')">
                <div class="grid gap-5 sm:grid-cols-2">
                    <x-ui.select name="reason" :label="__('Return reason')" :options="ReturnReason::options()" :selected="old('reason')" :placeholder="__('Choose a reason')" required />
                    <x-ui.select name="refund_method" :label="__('Refund method')" :options="MoneyMethod::options()" :selected="old('refund_method', 'cash')" required />
                    <x-ui.textarea name="notes" :label="__('Notes')" :value="old('notes')" rows="2" maxlength="1000" class="sm:col-span-2" />
                </div>
                @error('sale_id')
                    <p class="mt-3 text-sm text-red-600" role="alert">{{ $message }}</p>
                @enderror

                <div class="mt-6 flex flex-col-reverse gap-2 sm:flex-row sm:justify-end">
                    <x-ui.button :href="route('returns.index')" variant="secondary">{{ __('Cancel') }}</x-ui.button>
                    <x-ui.button type="submit" name="status" value="pending" variant="secondary" x-bind:disabled="units === 0">{{ __('Save as pending') }}</x-ui.button>
                    <x-ui.button type="submit" name="status" value="completed" x-bind:disabled="units === 0" data-complete-return>
                        {{ __('Complete return & refund') }} <span class="tabular-nums" x-text="format(total)"></span>
                    </x-ui.button>
                </div>
            </x-ui.card>
        </form>
    @endif
</x-app-layout>
