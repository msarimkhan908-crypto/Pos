@use('App\Support\Money')
@use('App\Support\Format')

<x-app-layout>
    <x-slot name="title">{{ __('Edit Sale :invoice', ['invoice' => $sale->invoice_number]) }}</x-slot>

    <x-ui.page-header
        :title="__('Edit Sale')"
        :description="$sale->invoice_number"
    >
        <x-slot:actions>
            <x-ui.button :href="route('orders.show', $sale)" variant="secondary" icon="arrow-left">
                {{ __('Back') }}
            </x-ui.button>
        </x-slot:actions>
    </x-ui.page-header>

    @if (session('warning'))
        <x-ui.alert tone="warning" class="mb-4">{{ session('warning') }}</x-ui.alert>
    @endif

    <div class="mx-auto max-w-2xl space-y-6">
        <form method="POST" action="{{ route('sales.update', $sale) }}">
            @csrf
            @method('PATCH')

            {{-- Summary strip --}}
            <x-ui.card>
                <div class="grid grid-cols-3 gap-4 text-sm">
                    <div>
                        <p class="text-gray-500">{{ __('Invoice') }}</p>
                        <p class="font-semibold text-gray-900">{{ $sale->invoice_number }}</p>
                    </div>
                    <div>
                        <p class="text-gray-500">{{ __('Order No.') }}</p>
                        <p class="font-semibold text-gray-900">{{ $sale->order_no ?? '—' }}</p>
                    </div>
                    <div>
                        <p class="text-gray-500">{{ __('Date') }}</p>
                        <p class="font-semibold text-gray-900">{{ Format::date($sale->completed_at) }}</p>
                    </div>
                </div>
            </x-ui.card>

            {{-- Reason (required) --}}
            <x-ui.card :title="__('Reason for Edit')" :description="__('Required. Shown in the audit history.')">
                <x-ui.textarea
                    name="reason"
                    :label="__('Reason')"
                    :value="old('reason')"
                    rows="2"
                    maxlength="500"
                    required
                    :placeholder="__('e.g. Customer requested name change')"
                />
            </x-ui.card>

            {{-- Order Items --}}
            <x-ui.card :title="__('Order Items')">
                <div
                    x-data="{
                        items: @js($sale->items->map(fn ($item) => [
                            'key'         => 'ex_' . $item->id,
                            'existing_id' => $item->id,
                            'product_id'  => null,
                            'variant_id'  => null,
                            'name'        => $item->product_name . ($item->variant_name ? ' – ' . $item->variant_name : ''),
                            'quantity'    => (int) $item->quantity,
                        ])->values()->all()),
                        search: '',
                        results: [],
                        pickVariants: null,
                        _k: 0,
                        async doSearch() {
                            const q = this.search.trim();
                            if (q.length < 2) { this.results = []; return; }
                            try {
                                const r = await fetch('{{ route('pos.products') }}?search=' + encodeURIComponent(q), { headers: { 'X-Requested-With': 'XMLHttpRequest' } });
                                this.results = (await r.json()).products ?? [];
                            } catch (e) { this.results = []; }
                        },
                        dropdownStyle() {
                            const inp = this.$refs.searchInput;
                            if (!inp) return {};
                            const rect = inp.getBoundingClientRect();
                            return { position: 'fixed', zIndex: '9999', top: (rect.bottom + 4) + 'px', left: rect.left + 'px', width: rect.width + 'px' };
                        },
                        add(product, variantId) {
                            const v = variantId ? (product.variants.find(x => x.id === variantId) ?? null) : null;
                            this.items.push({ key: 'new_' + (++this._k), existing_id: null, product_id: product.id, variant_id: variantId ?? null, name: product.name + (v ? ' – ' + v.name : ''), quantity: 1 });
                            this.search = ''; this.results = []; this.pickVariants = null;
                        },
                        pick(product) {
                            if (product.variants.length === 0) { this.add(product, null); }
                            else { this.pickVariants = product; this.results = []; }
                        }
                    }"
                    x-on:click.outside="results = []; pickVariants = null"
                >
                    {{-- Item rows --}}
                    <div class="divide-y divide-gray-100 dark:divide-gray-700">
                        <template x-for="(item, i) in items" :key="item.key">
                            <div class="flex items-center gap-3 py-3">
                                <input type="hidden" :name="'items['+i+'][existing_id]'" :value="item.existing_id" :disabled="!item.existing_id">
                                <input type="hidden" :name="'items['+i+'][product_id]'"  :value="item.product_id"  :disabled="!!item.existing_id">
                                <input type="hidden" :name="'items['+i+'][variant_id]'"  :value="item.variant_id"  :disabled="!!item.existing_id || !item.variant_id">
                                <p class="flex-1 truncate text-sm font-medium text-gray-900 dark:text-gray-100" x-text="item.name"></p>
                                <div class="flex items-center gap-1.5">
                                    <button type="button" x-on:click="if (item.quantity > 1) item.quantity--"
                                        class="inline-flex size-7 items-center justify-center rounded-lg border border-gray-300 text-gray-500 hover:bg-gray-50 dark:border-gray-600 dark:text-gray-400 dark:hover:bg-gray-700">
                                        <x-icon name="minus" class="size-3.5" />
                                    </button>
                                    <input type="number" :name="'items['+i+'][quantity]'" x-model.number="item.quantity" min="1"
                                        class="w-14 rounded-lg border-gray-300 text-center text-sm dark:bg-gray-700 dark:border-gray-600 dark:text-gray-100">
                                    <button type="button" x-on:click="item.quantity++"
                                        class="inline-flex size-7 items-center justify-center rounded-lg border border-gray-300 text-gray-500 hover:bg-gray-50 dark:border-gray-600 dark:text-gray-400 dark:hover:bg-gray-700">
                                        <x-icon name="plus" class="size-3.5" />
                                    </button>
                                    <button type="button" x-on:click="items.splice(i, 1)"
                                        class="ml-1 inline-flex size-8 items-center justify-center rounded-lg text-red-400 hover:bg-red-50 hover:text-red-600 dark:hover:bg-red-900/20">
                                        <x-icon name="trash" class="size-4" />
                                    </button>
                                </div>
                            </div>
                        </template>
                    </div>
                    <p x-show="items.length === 0" class="py-3 text-center text-sm text-gray-500">{{ __('All items removed.') }}</p>

                    {{-- Add product search --}}
                    <div class="mt-4 border-t border-gray-100 pt-4 dark:border-gray-700">
                        <p class="mb-2 text-xs font-semibold uppercase tracking-wide text-gray-400 dark:text-gray-500">{{ __('Add Product') }}</p>
                        <input type="text" x-model="search"
                            x-ref="searchInput"
                            x-on:input.debounce.300ms="doSearch()"
                            x-on:keydown.escape="results = []; pickVariants = null"
                            placeholder="{{ __('Type product name or SKU to search…') }}"
                            autocomplete="off"
                            class="block w-full rounded-lg border-gray-300 text-sm dark:bg-gray-700 dark:border-gray-600 dark:text-gray-100 dark:placeholder-gray-400">

                        {{-- Search results — teleported to body so card overflow:hidden cannot clip it --}}
                        <template x-teleport="body">
                            <div x-show="results.length > 0"
                                x-bind:style="dropdownStyle()"
                                x-on:click.stop
                                class="max-h-56 overflow-y-auto rounded-lg border border-gray-200 bg-white shadow-xl dark:border-gray-700 dark:bg-gray-800">
                                <template x-for="product in results.slice(0, 10)" :key="product.id">
                                    <button type="button" x-on:click="pick(product)"
                                        class="flex w-full items-center justify-between px-3 py-2.5 text-left text-sm hover:bg-gray-50 dark:hover:bg-gray-700">
                                        <span class="font-medium text-gray-900 dark:text-gray-100" x-text="product.name"></span>
                                        <span class="text-xs text-gray-500 dark:text-gray-400"
                                            x-text="product.variants.length ? product.variants.length + ' {{ __('options') }}' : product.price"></span>
                                    </button>
                                </template>
                            </div>
                        </template>

                        {{-- Variant picker — also teleported --}}
                        <template x-teleport="body">
                            <div x-show="pickVariants !== null"
                                x-bind:style="dropdownStyle()"
                                x-on:click.stop
                                class="max-h-56 overflow-y-auto rounded-lg border border-gray-200 bg-white shadow-xl dark:border-gray-700 dark:bg-gray-800">
                                <div class="flex items-center gap-2 border-b border-gray-100 px-3 py-2 dark:border-gray-700">
                                    <button type="button" x-on:click="pickVariants = null; doSearch()"
                                        class="text-gray-400 hover:text-gray-600 dark:hover:text-gray-300">
                                        <x-icon name="arrow-left" class="size-4" />
                                    </button>
                                    <span class="text-sm font-semibold text-gray-900 dark:text-gray-100" x-text="pickVariants?.name"></span>
                                </div>
                                <template x-for="variant in (pickVariants?.variants ?? [])" :key="variant.id">
                                    <button type="button" x-on:click="add(pickVariants, variant.id)"
                                        class="flex w-full items-center justify-between px-3 py-2.5 text-left text-sm hover:bg-gray-50 dark:hover:bg-gray-700">
                                        <span class="text-gray-900 dark:text-gray-100" x-text="variant.name"></span>
                                        <span class="text-xs text-gray-500 dark:text-gray-400" x-text="variant.price"></span>
                                    </button>
                                </template>
                            </div>
                        </template>
                    </div>
                </div>
            </x-ui.card>

            {{-- Customer details --}}
            <x-ui.card :title="__('Customer Details')">
                <div class="grid gap-4 sm:grid-cols-2">
                    <x-ui.input
                        name="customer_name"
                        :label="__('Customer Name')"
                        :value="old('customer_name', $sale->customer_name)"
                        :placeholder="__('Walk-in Customer')"
                        maxlength="150"
                    />
                    <x-ui.input
                        name="customer_phone"
                        :label="__('Phone')"
                        :value="old('customer_phone', $sale->customer_phone)"
                        maxlength="30"
                    />
                    <div class="sm:col-span-2">
                        <x-ui.textarea
                            name="customer_address"
                            :label="__('Address')"
                            :value="old('customer_address', $sale->customer_address)"
                            rows="2"
                            maxlength="500"
                        />
                    </div>
                </div>
            </x-ui.card>

            {{-- Notes --}}
            <x-ui.card :title="__('Notes')">
                <x-ui.textarea
                    name="notes"
                    :label="__('Note')"
                    :value="old('notes', $sale->notes)"
                    rows="3"
                    maxlength="1000"
                    :placeholder="__('Optional note…')"
                />
            </x-ui.card>

            <div class="flex items-center justify-end gap-3">
                <x-ui.button :href="route('orders.show', $sale)" variant="secondary">{{ __('Cancel') }}</x-ui.button>
                <x-ui.button type="submit" icon="pencil">{{ __('Save Changes') }}</x-ui.button>
            </div>
        </form>

        {{-- Edit history --}}
        @if ($sale->edits->isNotEmpty())
            <x-ui.card :title="__('Edit History')">
                <ul class="divide-y divide-gray-100">
                    @foreach ($sale->edits as $edit)
                        <li class="py-3 text-sm">
                            <div class="flex items-center justify-between">
                                <span class="font-medium text-gray-900">{{ $edit->editor?->name ?? __('Unknown') }}</span>
                                <span class="text-gray-500">{{ Format::dateTime($edit->created_at) }}</span>
                            </div>
                            <p class="mt-0.5 text-gray-600">{{ $edit->reason }}</p>
                        </li>
                    @endforeach
                </ul>
            </x-ui.card>
        @endif
    </div>
</x-app-layout>
