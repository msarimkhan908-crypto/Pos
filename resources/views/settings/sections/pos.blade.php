@php
    $receipt = settings()->receipt();
    $kot = settings()->kot();
    $widthLabel = fn ($w) => $w === 'a4' ? 'A4' : $w;
    $posColorFields = [
        'bg_color'        => [__('Product area background'), __('Background behind product cards and the search bar.'), '#f3f4f6'],
        'header_bg_color' => [__('Header bar background'),  __('Top strip with the POS title and held-sales button.'), '#ffffff'],
        'cart_bg_color'   => [__('Cart panel background'),  __('Right panel showing cart items, totals and checkout.'), '#ffffff'],
    ];
@endphp

<x-ui.card :title="__('Screen colours')" :description="__('Personalise the POS screen to match your brand.')">
    <div class="grid gap-6 sm:grid-cols-3">
        @foreach ($posColorFields as $key => [$label, $hint, $default])
            @php($val = old($key, settings("pos.{$key}") ?: $default))
            <div x-data="{ color: '{{ $val }}' }">
                <label class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-300">{{ $label }}</label>
                <div class="flex items-center gap-2">
                    <input type="color" x-model="color" class="h-10 w-14 cursor-pointer rounded-md border border-gray-300 p-1 dark:border-gray-600" aria-label="{{ $label }}">
                    <input type="text" name="{{ $key }}" x-model="color" maxlength="7" pattern="#[0-9a-fA-F]{6}"
                        class="block w-28 rounded-lg border-gray-300 font-mono text-sm uppercase focus:border-primary-500 focus:ring-primary-500 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-100">
                </div>
                <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">{{ $hint }}</p>
                @error($key)<p class="mt-1 text-sm text-red-600">{{ $message }}</p>@enderror
            </div>
        @endforeach
    </div>
</x-ui.card>

<x-ui.card :title="__('Automatic printing')" :description="__('What prints by itself when the cashier completes a sale in the POS.')">
    <div class="space-y-4">
        <x-ui.checkbox name="auto_receipt" :label="__('Print the customer receipt after a completed sale')" :checked="old('auto_receipt', settings('print.auto_receipt'))" />
        <x-ui.checkbox name="auto_kot" :label="__('Print the kitchen order ticket (KOT) when it is created')" :description="__('Only orders with kitchen items create a KOT.')" :checked="old('auto_kot', settings('print.auto_kot'))" />
    </div>
</x-ui.card>

<x-ui.card :title="__('Printer layout')">
    @php($layout = old('layout', settings('print.layout')))
    <div class="grid gap-3 sm:grid-cols-2">
        <label class="flex items-start gap-3 rounded-lg p-3 ring-1 ring-gray-200 hover:bg-gray-50 dark:ring-gray-700 dark:hover:bg-gray-700/50">
            <input type="radio" name="layout" value="combined" @checked($layout === 'combined') class="mt-0.5 border-gray-300 text-primary-600 focus:ring-primary-500 dark:border-gray-600">
            <span><span class="block text-sm font-medium text-gray-900 dark:text-gray-100">{{ __('One print job') }}</span><span class="block text-xs text-gray-500 dark:text-gray-400">{{ __('Receipt and KOT together, each on its own page (the printer cuts between them). One print dialog.') }}</span></span>
        </label>
        <label class="flex items-start gap-3 rounded-lg p-3 ring-1 ring-gray-200 hover:bg-gray-50 dark:ring-gray-700 dark:hover:bg-gray-700/50">
            <input type="radio" name="layout" value="separate" @checked($layout === 'separate') class="mt-0.5 border-gray-300 text-primary-600 focus:ring-primary-500 dark:border-gray-600">
            <span><span class="block text-sm font-medium text-gray-900 dark:text-gray-100">{{ __('Separate print jobs') }}</span><span class="block text-xs text-gray-500 dark:text-gray-400">{{ __('The receipt prints first, then the KOT, e.g. to send them to different printers.') }}</span></span>
        </label>
    </div>
</x-ui.card>

<x-ui.card :title="__('Paper')">
    <dl class="grid gap-3 text-sm sm:grid-cols-2">
        <div class="rounded-lg bg-gray-50 p-3"><dt class="text-gray-500">{{ __('Customer receipt width') }}</dt><dd class="mt-1 font-semibold text-gray-900" data-receipt-width>{{ $widthLabel($receipt['width']) }}</dd></div>
        <div class="rounded-lg bg-gray-50 p-3"><dt class="text-gray-500">{{ __('Kitchen receipt width') }}</dt><dd class="mt-1 font-semibold text-gray-900">{{ $widthLabel($kot['width']) }}</dd></div>
    </dl>
    <p class="mt-3 text-sm text-gray-500">
        {{ __('Widths are set with each design, where you can see the preview:') }}
        <a href="{{ route('settings.receipt') }}" class="font-semibold text-primary-700 hover:underline">{{ __('Customer receipt') }}</a> ·
        <a href="{{ route('settings.kot') }}" class="font-semibold text-primary-700 hover:underline">{{ __('Kitchen receipt') }}</a>
    </p>
</x-ui.card>

<x-ui.card :title="__('Printing without the print dialog')">
    <p class="text-sm text-gray-600">{{ __('Browsers always show the print dialog. On the till PC, open the POS with Chrome kiosk printing to print straight to the default printer:') }}</p>
    <code class="mt-2 block overflow-x-auto rounded-lg bg-gray-900 px-3 py-2 text-xs text-gray-100">chrome.exe --kiosk --kiosk-printing {{ route('pos.index') }}</code>
</x-ui.card>

{{-- Desktop-only sections --}}
<div x-data="desktopPos()" x-show="isDesktop" style="display:none" class="space-y-6">

    {{-- Printer setup --}}
    <x-ui.card :title="__('Printer setup')" :description="__('Choose which printer to use for receipts and KOTs. If a saved printer is no longer found, a warning appears on the POS screen.')">
        <div class="grid gap-5 sm:grid-cols-2">
            <div>
                <label class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-300">{{ __('Receipt printer') }}</label>
                <select name="desktop_printer" x-model="receiptPrinter"
                    class="block w-full rounded-lg border-gray-300 text-sm focus:border-primary-500 focus:ring-primary-500 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-100">
                    <option value="">{{ __('Default printer') }}</option>
                    <template x-for="p in printers" :key="p.name">
                        <option :value="p.name" x-text="p.name + (p.isDefault ? ' ✓' : '')"></option>
                    </template>
                </select>
                <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">{{ __('Leave blank to use the system default printer.') }}</p>
            </div>
            <div>
                <label class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-300">{{ __('KOT printer') }}</label>
                <select name="kot_printer" x-model="kotPrinter"
                    class="block w-full rounded-lg border-gray-300 text-sm focus:border-primary-500 focus:ring-primary-500 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-100">
                    <option value="">{{ __('Same as receipt printer') }}</option>
                    <template x-for="p in printers" :key="p.name">
                        <option :value="p.name" x-text="p.name + (p.isDefault ? ' ✓' : '')"></option>
                    </template>
                </select>
                <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">{{ __('Leave blank to use the same printer as receipts.') }}</p>
            </div>
        </div>
        <div class="mt-4 flex flex-wrap gap-3">
            <button type="button" @click="testPrint(receiptPrinter, receiptWidth)"
                class="rounded-lg bg-primary-50 px-4 py-2 text-sm font-semibold text-primary-700 hover:bg-primary-100 dark:bg-primary-900/30 dark:text-primary-300">
                {{ __('Test receipt printer') }}
            </button>
            <button type="button" @click="testPrint(kotPrinter || receiptPrinter, kotWidth)"
                class="rounded-lg bg-primary-50 px-4 py-2 text-sm font-semibold text-primary-700 hover:bg-primary-100 dark:bg-primary-900/30 dark:text-primary-300">
                {{ __('Test KOT printer') }}
            </button>
            <button type="button" @click="calibrate(receiptPrinter, receiptWidth)"
                class="rounded-lg bg-gray-100 px-4 py-2 text-sm font-semibold text-gray-700 hover:bg-gray-200 dark:bg-gray-700 dark:text-gray-200 dark:hover:bg-gray-600">
                {{ __('Print calibration slip') }}
            </button>
        </div>
        <p x-show="statusMsg" x-text="statusMsg" class="mt-2 text-sm" :class="statusOk ? 'text-green-600' : 'text-red-600'"></p>
    </x-ui.card>

    {{-- Diagnostics --}}
    <x-ui.card :title="__('Diagnostics')" :description="__('Startup check results and recent print jobs. Export a ZIP for support.')">
        <div x-show="startupCheck">
            <p class="mb-2 text-sm font-medium text-gray-700 dark:text-gray-300">{{ __('Startup checks') }}</p>
            <ul class="mb-4 space-y-1 text-sm">
                <template x-for="c in startupCheck?.checks ?? []" :key="c.name">
                    <li class="flex items-center gap-2">
                        <span :class="c.ok === true ? 'text-green-600' : c.ok === false ? 'text-red-600' : 'text-yellow-600'" class="font-mono">
                            <span x-show="c.ok === true">✓</span>
                            <span x-show="c.ok === false">✗</span>
                            <span x-show="c.ok === null">?</span>
                        </span>
                        <span x-text="c.name" class="font-mono text-xs"></span>
                        <span x-text="c.detail ?? ''" class="text-xs text-gray-500 dark:text-gray-400"></span>
                    </li>
                </template>
            </ul>
        </div>

        <div x-show="history.length > 0">
            <p class="mb-2 text-sm font-medium text-gray-700 dark:text-gray-300">{{ __('Recent print jobs') }} (<span x-text="history.length"></span>)</p>
            <div class="overflow-x-auto">
                <table class="w-full text-xs">
                    <thead><tr class="text-left text-gray-500">
                        <th class="pb-1 pr-3">{{ __('Time') }}</th>
                        <th class="pb-1 pr-3">{{ __('Result') }}</th>
                        <th class="pb-1 pr-3">{{ __('Printer') }}</th>
                        <th class="pb-1">{{ __('Width') }}</th>
                    </tr></thead>
                    <tbody>
                        <template x-for="j in history" :key="j.jobId">
                            <tr class="border-t border-gray-100 dark:border-gray-700">
                                <td class="py-1 pr-3 text-gray-500" x-text="new Date(j.ts).toLocaleString()"></td>
                                <td class="py-1 pr-3" :class="j.result === 'ok' ? 'text-green-600' : 'text-red-600'" x-text="j.result"></td>
                                <td class="py-1 pr-3 text-gray-700 dark:text-gray-300" x-text="j.printer"></td>
                                <td class="py-1 text-gray-500" x-text="j.width ?? ''"></td>
                            </tr>
                        </template>
                    </tbody>
                </table>
            </div>
        </div>

        <div class="mt-4 flex flex-wrap gap-3">
            <button type="button" @click="window.desktop.openLastPrint().then(r => { if (!r.ok) alert(r.reason); })"
                class="rounded-lg bg-gray-100 px-4 py-2 text-sm font-semibold text-gray-700 hover:bg-gray-200 dark:bg-gray-700 dark:text-gray-200 dark:hover:bg-gray-600">
                {{ __('Open last print PDF') }}
            </button>
            <button type="button" @click="exportDiag()"
                class="rounded-lg bg-gray-100 px-4 py-2 text-sm font-semibold text-gray-700 hover:bg-gray-200 dark:bg-gray-700 dark:text-gray-200 dark:hover:bg-gray-600">
                {{ __('Export diagnostics ZIP') }}
            </button>
        </div>
        <p class="mt-2 text-xs text-gray-500">{{ __('The last print PDF and diagnostics ZIP contain no customer data.') }}</p>
    </x-ui.card>
</div>

<script>
function desktopPos() {
    return {
        isDesktop:     typeof window.desktop !== 'undefined',
        printers:      [],
        receiptPrinter: '{{ old('desktop_printer', settings('receipt.desktop_printer') ?? '') }}',
        kotPrinter:    '{{ old('kot_printer', settings('receipt.kot_printer') ?? '') }}',
        receiptWidth:  '{{ settings()->receipt()['width'] ?? '80mm' }}',
        kotWidth:      '{{ settings()->kot()['width'] ?? '80mm' }}',
        startupCheck:  null,
        history:       [],
        statusMsg:     '',
        statusOk:      true,

        async init() {
            if (!this.isDesktop) return;
            this.printers     = await window.desktop.getPrinters().catch(() => []);
            this.startupCheck = await window.desktop.getStartupCheck().catch(() => null);
            this.history      = await window.desktop.getPrintHistory().catch(() => []);
        },

        async testPrint(printer, width) {
            this.statusMsg = '{{ __('Printing test slip…') }}';
            this.statusOk  = true;
            try {
                await window.desktop.printTest(printer || '');
                this.statusMsg = '{{ __('Test slip sent!') }}';
            } catch (e) {
                this.statusOk  = false;
                this.statusMsg = '{{ __('Test print failed') }}: ' + e.message;
            }
        },

        async calibrate(printer, width) {
            this.statusMsg = '{{ __('Printing calibration slip…') }}';
            this.statusOk  = true;
            try {
                await window.desktop.printCalibration({ printerName: printer || '', width: width || '80mm' });
                this.statusMsg = '{{ __('Calibration slip sent!') }}';
            } catch (e) {
                this.statusOk  = false;
                this.statusMsg = '{{ __('Calibration print failed') }}: ' + e.message;
            }
        },

        async exportDiag() {
            this.statusMsg = '{{ __('Exporting…') }}';
            this.statusOk  = true;
            try {
                const r = await window.desktop.exportDiagnostics();
                this.statusMsg = r.ok ? '{{ __('Saved to desktop') }}' : ('{{ __('Export failed') }}: ' + r.reason);
                this.statusOk  = r.ok;
            } catch (e) {
                this.statusOk  = false;
                this.statusMsg = '{{ __('Export failed') }}: ' + e.message;
            }
        },
    };
}
</script>
