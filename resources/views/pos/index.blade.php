@php
    $config = [
        'urls' => [
            'products' => route('pos.products'),
            'lookup' => route('pos.lookup'),
            'quote' => route('pos.quote'),
            'held' => route('pos.held.index'),
            'hold' => route('pos.held.store'),
            'resume' => route('pos.held.resume', ['sale' => '__ID__']),
            'discard' => route('pos.held.destroy', ['sale' => '__ID__']),
            'checkout' => route('sales.store'),
            'tables' => route('pos.tables'),
            'tableStatus' => route('restaurant.tables.status', ['table' => '__ID__']),
            'history' => route('pos.history'),
        ],
        'currency' => \App\Support\Money::currency(),
        'currencySymbol' => (string) settings('general.currency_symbol'),
        'hideTrailingZeros' => (bool) settings('general.hide_trailing_zeros', true),
        'customers' => $customers,
        'canSaveCustomer' => auth()->user()->can('customers.create'),
        'print' => ['receipt' => (bool) settings('print.auto_receipt'), 'kot' => (bool) settings('print.auto_kot'), 'layout' => settings('print.layout')],
        'canEdit' => auth()->user()->can('sales.edit'),
        'printers' => ['receipt' => settings('receipt.desktop_printer'), 'kot' => settings('receipt.kot_printer')],
        'messages' => [
            'added' => __('added'),
            'walkIn' => __('Walk-in Customer'),
            'reserved' => __(':table is reserved. Mark it Available or Occupied first to seat guests.'),
            'selectTable' => __('Select a table for this Dine In order.'),
            'notFound' => __('Product not found.'),
            'productsFailed' => __('Products could not be loaded. Check the connection and try again.'),
            'server' => __('Something went wrong. Please try again.'),
            'session' => __('Your session has expired. Reload the page and sign in again.'),
            'forbidden' => __('You do not have permission to do that.'),
            'chooseVariant' => __('Choose an option first.'),
            'empty' => __('The cart is empty.'),
            'cartNotEmpty' => __('Hold or clear the current sale before resuming another.'),
            'dropped' => __('Resumed, but these items are no longer available and were removed:'),
            'enterCash' => __('Enter the cash received.'),
            'cashShort' => __('Cash received is less than the total.'),
            'cardMismatch' => __('The card amount must equal the total.'),
            'mixedBoth' => __('Enter both a cash and a card amount.'),
            'mixedMismatch' => __('Cash and card must add up to the total.'),
        ],
    ];
@endphp
<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="h-full">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="csrf-token" content="{{ csrf_token() }}">

        <title>{{ __('POS') }} · {{ config('app.name', 'Laravel') }}</title>

        <link rel="preconnect" href="https://fonts.bunny.net">
        <link href="https://fonts.bunny.net/css?family=figtree:400,500,600,700&display=swap" rel="stylesheet" />

        @vite(['resources/css/app.css', 'resources/js/app.js'])
        <x-theme />
        <style>:root{--pos-bg:{{ settings('pos.bg_color') ?: '#f3f4f6' }};--pos-header-bg:{{ settings('pos.header_bg_color') ?: '#ffffff' }};--pos-cart-bg:{{ settings('pos.cart_bg_color') ?: '#ffffff' }};}</style>
    </head>
    <body class="h-full bg-gray-100 font-sans text-gray-900 antialiased" style="background-color:var(--pos-bg)">
        <div
            x-data="pos(@js($config))"
            x-on:keydown.window="shortcut($event)"
            class="flex min-h-full flex-col md:h-screen"
            data-pos
        >
            {{-- Top bar --}}
            <header class="flex h-14 shrink-0 items-center gap-3 border-b border-gray-200 bg-white px-4" style="background-color:var(--pos-header-bg)">
                <a href="{{ route('dashboard') }}" class="inline-flex items-center gap-1.5 rounded-lg px-2 py-1.5 text-sm font-medium text-gray-600 hover:bg-gray-100 hover:text-gray-900">
                    <x-icon name="arrow-left" class="size-4" />
                    <span class="hidden sm:inline">{{ __('Dashboard') }}</span>
                </a>
                <div class="flex items-center gap-2">
                    <span class="flex size-8 items-center justify-center rounded-lg bg-primary-600 text-white"><x-icon name="pos" class="size-4" /></span>
                    <h1 class="text-base font-semibold">{{ __('POS') }}</h1>
                </div>

                <div class="ml-auto flex items-center gap-2">
                    <button type="button" x-on:click="openHistory()" class="inline-flex items-center gap-2 rounded-lg px-3 py-2 text-sm font-semibold text-gray-700 ring-1 ring-inset ring-gray-300 hover:bg-gray-50">
                        <x-icon name="clock" class="size-4" />
                        <span class="hidden sm:inline">{{ __('History') }}</span>
                    </button>
                    <button type="button" x-on:click="openHeld()" class="relative inline-flex items-center gap-2 rounded-lg px-3 py-2 text-sm font-semibold text-gray-700 ring-1 ring-inset ring-gray-300 hover:bg-gray-50">
                        <x-icon name="history" class="size-4" />
                        <span>{{ __('Held sales') }}</span>
                        <span x-show="heldCount > 0" x-cloak x-text="heldCount" class="rounded-full bg-amber-500 px-1.5 py-0.5 text-xs font-bold leading-none text-white"></span>
                    </button>
                    <span class="hidden items-center gap-1.5 text-sm text-gray-500 lg:inline-flex">
                        <x-icon name="user" class="size-4" /> {{ auth()->user()->name }}
                    </span>
                </div>
            </header>

            <div class="grid min-h-0 flex-1 grid-cols-1 pb-20 md:grid-cols-[minmax(0,1fr)_360px] md:pb-0 lg:grid-cols-[minmax(0,1fr)_400px] xl:grid-cols-[minmax(0,1fr)_440px]">
                {{-- Products --}}
                <section class="flex min-h-0 flex-col" aria-label="{{ __('Products') }}">
                    <div class="grid shrink-0 gap-2 border-b border-gray-200 bg-white p-3 sm:grid-cols-[minmax(0,1fr)_minmax(0,16rem)]">
                        <label class="relative block">
                            <span class="sr-only">{{ __('Search products') }}</span>
                            <x-icon name="search" class="pointer-events-none absolute left-3 top-1/2 size-5 -translate-y-1/2 text-gray-400" />
                            <input
                                x-ref="search"
                                x-model="search"
                                type="search"
                                autocomplete="off"
                                placeholder="{{ __('Search name, SKU, barcode (F2)') }}"
                                class="block w-full rounded-lg border-gray-300 py-3 pl-10 text-base focus:border-primary-500 focus:ring-primary-500 dark:bg-gray-700 dark:border-gray-600 dark:text-gray-100 dark:placeholder-gray-400"
                            >
                        </label>
                        <form x-on:submit.prevent="scan()" class="relative">
                            <label>
                                <span class="sr-only">{{ __('Scan barcode') }}</span>
                                <x-icon name="barcode" class="pointer-events-none absolute left-3 top-1/2 size-5 -translate-y-1/2 text-gray-400" />
                                <input
                                    x-ref="barcode"
                                    x-model="barcode"
                                    type="text"
                                    inputmode="numeric"
                                    autocomplete="off"
                                    autofocus
                                    placeholder="{{ __('Scan barcode (F4)') }}"
                                    class="block w-full rounded-lg border-gray-300 py-3 pl-10 text-base focus:border-primary-500 focus:ring-primary-500 dark:bg-gray-700 dark:border-gray-600 dark:text-gray-100 dark:placeholder-gray-400"
                                    data-barcode-input
                                >
                            </label>
                        </form>
                    </div>

                    {{-- Categories --}}
                    <nav class="flex shrink-0 gap-2 overflow-x-auto border-b border-gray-200 bg-white px-3 py-2 dark:border-gray-700 dark:bg-gray-800" aria-label="{{ __('Categories') }}">
                        <button type="button" x-on:click="selectCategory(null)"
                            x-bind:class="category === null ? 'bg-primary-600 text-white' : 'bg-gray-100 text-gray-700 hover:bg-gray-200'"
                            class="shrink-0 rounded-full px-4 py-2 text-sm font-semibold">{{ __('All') }}</button>
                        @foreach ($categories as $category)
                            <button type="button" x-on:click="selectCategory({{ $category->id }})"
                                x-bind:class="category === {{ $category->id }} ? 'bg-primary-600 text-white' : 'bg-gray-100 text-gray-700 hover:bg-gray-200'"
                                class="shrink-0 whitespace-nowrap rounded-full px-4 py-2 text-sm font-semibold">{{ $category->name }}</button>
                        @endforeach
                    </nav>

                    {{-- Product grid --}}
                    <div class="min-h-0 flex-1 overflow-y-auto p-3" x-bind:aria-busy="loadingProducts">
                        <p x-show="productsError" x-cloak class="rounded-lg bg-red-50 p-4 text-sm text-red-700" x-text="productsError"></p>

                        <div x-show="!loadingProducts && !productsError && products.length === 0" x-cloak class="py-16 text-center">
                            <x-icon name="search" class="mx-auto size-8 text-gray-300" />
                            <p class="mt-2 text-sm font-medium text-gray-700">{{ __('No products found') }}</p>
                            <p class="text-sm text-gray-500">{{ __('Try another search or category.') }}</p>
                        </div>

                        <div class="grid grid-cols-2 gap-3 sm:grid-cols-3 xl:grid-cols-4 2xl:grid-cols-5">
                            <template x-for="product in products" :key="product.id">
                                <button
                                    type="button"
                                    x-on:click="addProduct(product)"
                                    class="group flex flex-col overflow-hidden rounded-xl bg-white text-left shadow-sm ring-1 ring-gray-200 transition hover:ring-2 hover:ring-primary-400 focus:outline-none focus-visible:ring-2 focus-visible:ring-primary-600 active:scale-[.98]"
                                    data-product-card
                                >
                                    <div class="flex aspect-[4/3] w-full items-center justify-center overflow-hidden bg-gray-100">
                                        <template x-if="product.image_url">
                                            <img x-bind:src="product.image_url" x-bind:alt="product.name" class="size-full object-cover" loading="lazy">
                                        </template>
                                        <template x-if="!product.image_url">
                                            <span
                                                class="flex size-full items-center justify-center text-5xl select-none"
                                                x-bind:style="'background-color:' + (product.placeholder_color || '#6b7280')"
                                                x-text="product.placeholder_icon || '🍽️'"
                                            ></span>
                                        </template>
                                    </div>
                                    <div class="flex flex-1 flex-col p-3">
                                        <p class="line-clamp-2 text-sm font-semibold text-gray-900" x-text="product.name"></p>
                                        <p class="mt-0.5 text-xs text-gray-500" x-show="product.variants.length || product.modifier_groups.length"
                                            x-text="product.variants.length ? product.variants.length + ' {{ __('options') }}' : '{{ __('Add-ons available') }}'"></p>
                                        <div class="mt-auto flex items-center justify-between gap-2 pt-2">
                                            <span class="text-sm font-bold tabular-nums text-gray-900">
                                                <span x-show="product.variants.length" class="text-xs font-normal text-gray-500">{{ __('from') }}</span>
                                                <span x-text="format(product.variants.length ? Math.min(...product.variants.map(v => parseFloat(v.price))) : product.price)"></span>
                                            </span>
                                            <span class="rounded-md bg-primary-50 px-2 py-1 text-xs font-bold uppercase text-primary-700 group-hover:bg-primary-600 group-hover:text-white">{{ __('Add') }}</span>
                                        </div>
                                    </div>
                                </button>
                            </template>
                        </div>

                        <p x-show="more" x-cloak class="mt-4 text-center text-xs text-gray-500">{{ __('Showing the first :count products. Search to narrow down.', ['count' => \App\Http\Controllers\PosController::PAGE_SIZE]) }}</p>
                    </div>
                </section>

                {{-- Cart: compact so the items get most of the height --}}
                <aside x-ref="cart" class="flex min-h-[32rem] flex-col border-t border-gray-200 bg-white md:min-h-0 md:border-l md:border-t-0" style="background-color:var(--pos-cart-bg)" aria-label="{{ __('Cart') }}">
                    {{-- Header + order type + customer --}}
                    <div class="shrink-0 space-y-2 border-b border-gray-200 px-3 py-2.5">
                        <div class="flex items-center justify-between gap-2">
                            <h2 class="flex items-center gap-2 text-base font-semibold">
                                <x-icon name="shopping-cart" class="size-5 text-gray-400" />
                                {{ __('Cart') }}
                                <span class="rounded-full bg-primary-600 px-2 py-0.5 text-xs font-bold text-white" x-show="itemCount" x-text="itemCount + ' ' + (itemCount === 1 ? '{{ __('item') }}' : '{{ __('items') }}')" data-cart-count></span>
                            </h2>
                            <button type="button" x-on:click="askClear()" x-bind:disabled="!lines.length" class="rounded-md px-2 py-1 text-xs font-semibold text-red-600 hover:bg-red-50 disabled:opacity-40">
                                {{ __('Clear cart') }}
                            </button>
                        </div>

                        <div class="flex items-center gap-1.5">
                            <div class="grid flex-1 grid-cols-3 gap-0.5 rounded-lg bg-gray-100 p-0.5" role="radiogroup" aria-label="{{ __('Order type') }}">
                                @foreach (\App\Enums\OrderType::options() as $value => $label)
                                    <button type="button" role="radio" x-on:click="setOrderType('{{ $value }}')"
                                        x-bind:aria-checked="orderType === '{{ $value }}'"
                                        x-bind:class="orderType === '{{ $value }}' ? 'bg-white text-gray-900 shadow-sm ring-1 ring-gray-200' : 'text-gray-500 hover:text-gray-900'"
                                        class="truncate rounded-md px-1 py-1.5 text-xs font-bold uppercase tracking-wide" data-order-type="{{ $value }}">{{ $label }}</button>
                                @endforeach
                            </div>
                            <button type="button" x-show="orderType === 'dine_in'" x-cloak x-on:click="openTables()" data-table-button
                                x-bind:class="tableId ? 'bg-primary-50 text-primary-800 ring-primary-200' : 'bg-amber-50 text-amber-800 ring-amber-300 animate-pulse'"
                                class="inline-flex max-w-[8rem] shrink-0 items-center gap-1 rounded-lg px-2 py-1.5 text-xs font-bold ring-1 ring-inset">
                                <x-icon name="table" class="size-4 shrink-0" />
                                <span class="truncate" x-text="tableId ? tableName : '{{ __('Table?') }}'"></span>
                            </button>
                            <button type="button" x-on:click="openNote('order')" data-order-note-button
                                x-bind:class="orderNote ? 'bg-yellow-100 text-yellow-900 ring-yellow-300' : 'bg-white text-gray-500 ring-gray-300 hover:text-gray-800'"
                                class="relative inline-flex size-8 shrink-0 items-center justify-center rounded-lg ring-1 ring-inset"
                                x-bind:title="orderNote || '{{ __('Order note') }}'" aria-label="{{ __('Order note') }}">
                                <x-icon name="pencil" class="size-4" />
                            </button>
                        </div>

                        <button type="button" x-on:click="openCustomer()" data-customer-button
                            class="flex w-full items-center justify-between gap-2 rounded-lg px-2.5 py-1.5 text-left text-sm ring-1 ring-inset ring-gray-200 hover:bg-gray-50">
                            <span class="flex min-w-0 items-center gap-2">
                                <x-icon name="user" class="size-4 shrink-0 text-gray-400" />
                                <span class="truncate font-medium" x-text="customerName"></span>
                                <span class="hidden truncate text-xs text-gray-500 sm:inline" x-show="customerLine" x-text="customerLine"></span>
                            </span>
                            <span class="flex shrink-0 items-center gap-1.5">
                                <span x-show="deliveryFee" x-cloak class="rounded-full bg-sky-50 px-2 py-0.5 text-[11px] font-semibold text-sky-700" x-text="'{{ __('Delivery') }} ' + format(deliveryFee)"></span>
                                <x-icon name="pencil" class="size-3.5 text-gray-400" />
                            </span>
                        </button>

                        <p x-show="orderNote" x-cloak class="truncate rounded-md bg-yellow-50 px-2 py-1 text-xs text-yellow-900">
                            <span class="font-semibold">{{ __('Note') }}:</span> <span x-text="orderNote"></span>
                        </p>
                    </div>

                    {{-- Items --}}
                    <div x-ref="cartList" class="min-h-0 flex-1 overflow-y-auto" data-cart-list>
                        <div x-show="!lines.length" class="flex h-full flex-col items-center justify-center p-8 text-center">
                            <x-icon name="shopping-cart" class="size-10 text-gray-300" />
                            <p class="mt-2 text-sm font-medium text-gray-700">{{ __('The cart is empty') }}</p>
                            <p class="text-sm text-gray-500">{{ __('Tap a product or scan a barcode to start a sale.') }}</p>
                        </div>

                        <ul class="divide-y divide-gray-100" x-show="lines.length" x-cloak>
                            <template x-for="(line, index) in lines" :key="line.key">
                                <li class="px-3 py-2 transition-colors duration-700" x-bind:data-key="line.key"
                                    x-bind:class="flashKey === line.key ? 'bg-primary-50' : 'bg-white'" data-cart-line>
                                    {{-- Row 1: name, details, line total --}}
                                    <div class="flex items-start justify-between gap-2">
                                        <div class="min-w-0 flex-1">
                                            <p class="text-sm font-semibold leading-snug text-gray-900">
                                                <span x-text="line.name"></span>
                                                <span x-show="line.variant_name" class="font-normal text-gray-500" x-text="'· ' + line.variant_name"></span>
                                                <span x-show="line.is_deal" class="ml-1 inline-flex items-center rounded bg-sky-100 px-1 py-0.5 text-[10px] font-medium text-sky-700">{{ __('Deal') }}</span>
                                            </p>
                                            <p class="truncate text-xs text-gray-500" x-show="line.quantity > 1 || line.modifiers.length">
                                                <span class="tabular-nums" x-text="line.quantity + ' × ' + format(line.unit_price)"></span>
                                                <span x-show="line.modifiers.length" x-text="' · + ' + line.modifiers.map(m => m.name).join(', ')"></span>
                                            </p>
                                            {{-- Deal components list --}}
                                            <p class="mt-0.5 text-xs text-gray-400" x-show="line.is_deal && line.deal_items && line.deal_items.length"
                                               x-text="line.deal_items.map(d => d.quantity + '× ' + d.component_name + (d.variant_name ? ' (' + d.variant_name + ')' : '')).join(', ')"></p>
                                            <p class="truncate text-xs font-semibold text-yellow-800" x-show="line.note" x-text="'» ' + line.note"></p>
                                        </div>
                                        <div class="shrink-0 text-right">
                                            <p class="text-sm font-bold tabular-nums text-gray-900" x-text="format(lineTotal(index))"></p>
                                            <p class="text-[11px] tabular-nums text-emerald-700" x-show="parseFloat(lineDiscount(index)) > 0" x-text="'−' + format(lineDiscount(index))"></p>
                                        </div>
                                    </div>

                                    {{-- Row 2: quantity + actions --}}
                                    <div class="mt-1.5 flex items-center gap-1">
                                        <div class="flex items-center rounded-lg ring-1 ring-gray-300">
                                            <button type="button" x-on:click="decrease(index)" class="flex size-8 items-center justify-center rounded-l-lg text-gray-700 hover:bg-gray-100" aria-label="{{ __('Decrease quantity') }}">
                                                <x-icon name="minus" class="size-4" />
                                            </button>
                                            <input type="number" min="1" max="9999" inputmode="numeric"
                                                x-bind:value="line.quantity"
                                                x-on:change="setQuantity(index, $event.target.value)"
                                                class="h-8 w-11 border-0 border-x border-gray-300 p-0 text-center text-sm font-bold tabular-nums focus:ring-2 focus:ring-primary-500 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-100"
                                                aria-label="{{ __('Quantity') }}">
                                            <button type="button" x-on:click="increase(index)" class="flex size-8 items-center justify-center rounded-r-lg text-gray-700 hover:bg-gray-100" aria-label="{{ __('Increase quantity') }}">
                                                <x-icon name="plus" class="size-4" />
                                            </button>
                                        </div>

                                        <button type="button" x-on:click="openNote('line', index)" data-line-note
                                            class="ml-auto inline-flex h-8 items-center gap-1 rounded-md px-2 text-xs font-semibold hover:bg-gray-100"
                                            x-bind:class="line.note ? 'text-yellow-800' : 'text-gray-500'" aria-label="{{ __('Item note') }}">
                                            <x-icon name="pencil" class="size-3.5" /> {{ __('Note') }}
                                        </button>
                                        <button type="button" x-on:click="openDiscount('line', index)"
                                            class="inline-flex h-8 items-center gap-1 rounded-md px-2 text-xs font-semibold hover:bg-gray-100"
                                            x-bind:class="line.discount_type ? 'text-emerald-700' : 'text-gray-500'" aria-label="{{ __('Item discount') }}">
                                            <x-icon name="percent" class="size-3.5" />
                                            <span x-text="discountLabel(line.discount_type, line.discount_value) || '{{ __('Disc.') }}'"></span>
                                        </button>
                                        <button type="button" x-on:click="remove(index)" class="flex size-8 items-center justify-center rounded-md text-gray-400 hover:bg-red-50 hover:text-red-600" aria-label="{{ __('Remove item') }}">
                                            <x-icon name="trash" class="size-4" />
                                        </button>
                                    </div>

                                    <p class="mt-1 text-xs text-red-600" x-show="lineError(index)" x-text="lineError(index)" role="alert"></p>
                                </li>
                            </template>
                        </ul>
                    </div>

                    {{-- Totals --}}
                    <div class="shrink-0 border-t border-gray-200 bg-gray-50 px-3 py-2">
                        <p class="mb-1.5 rounded-md bg-red-50 px-2 py-1.5 text-xs text-red-700" x-show="cartError" x-cloak x-text="cartError" role="alert"></p>

                        <dl class="grid grid-cols-2 gap-x-4 gap-y-0.5 text-xs tabular-nums" x-bind:class="quoting && 'opacity-60'">
                            <div class="flex justify-between"><dt class="text-gray-500">{{ __('Subtotal') }}</dt><dd class="font-medium text-gray-800" x-text="total('subtotal') !== null ? format(total('subtotal')) : '—'" data-total="subtotal"></dd></div>
                            <div class="flex justify-between"><dt class="text-gray-500">{{ settings('tax.label') }}</dt><dd class="font-medium text-gray-800" x-text="total('tax') !== null ? format(total('tax')) : '—'" data-total="tax"></dd></div>
                            <div class="flex items-center justify-between">
                                <dt>
                                    <button type="button" x-on:click="openDiscount('order')" x-bind:disabled="!lines.length"
                                        class="-ml-1 rounded px-1 font-semibold text-primary-700 hover:bg-primary-50 disabled:opacity-40"
                                        x-text="discountLabel(orderDiscount.type, orderDiscount.value) ? '{{ __('Discount') }} ' + discountLabel(orderDiscount.type, orderDiscount.value) : '{{ __('+ Discount') }}'"></button>
                                </dt>
                                <dd class="font-medium text-emerald-700" x-text="total('discount') === null ? '—' : (parseFloat(total('discount')) > 0 ? '−' : '') + format(total('discount'))" data-total="discount"></dd>
                            </div>
                            <div class="flex justify-between" x-show="deliveryFee" x-cloak><dt class="text-gray-500">{{ __('Delivery') }}</dt><dd class="font-medium text-gray-800" x-text="total('delivery_fee') !== null ? format(total('delivery_fee')) : '—'" data-total="delivery_fee"></dd></div>
                        </dl>
                        <div class="mt-1.5 flex items-baseline justify-between border-t border-gray-200 pt-1.5">
                            <span class="text-sm font-semibold text-gray-700">{{ __('Total') }}</span>
                            <span class="text-2xl font-bold tabular-nums text-gray-900" x-text="total('total') !== null ? format(total('total')) : '—'" data-total="total"></span>
                        </div>
                    </div>

                    {{-- Actions --}}
                    <div class="shrink-0 border-t border-gray-200 p-3">
                        <p class="mb-2 text-center text-xs font-semibold text-amber-700" x-show="lines.length && needsTable" x-cloak>{{ __('Select a table for this Dine In order.') }}</p>
                        <div class="flex gap-2">
                            <button type="button" x-on:click="holdSale()" x-bind:disabled="!lines.length || busy" title="{{ __('Hold sale') }}"
                                class="inline-flex flex-col items-center justify-center rounded-xl bg-amber-50 px-3 py-2 text-xs font-semibold text-amber-800 ring-1 ring-inset ring-amber-200 hover:bg-amber-100 disabled:opacity-40">
                                <x-icon name="pause" class="size-5" /> {{ __('Hold sale') }}
                            </button>
                            <button type="button" x-on:click="openHeld()" title="{{ __('Resume sale') }}"
                                class="relative inline-flex flex-col items-center justify-center rounded-xl bg-white px-3 py-2 text-xs font-semibold text-gray-700 ring-1 ring-inset ring-gray-300 hover:bg-gray-50">
                                <x-icon name="history" class="size-5" /> {{ __('Resume sale') }}
                                <span x-show="heldCount > 0" x-cloak x-text="heldCount" class="absolute -right-1.5 -top-1.5 rounded-full bg-amber-500 px-1.5 text-[11px] font-bold leading-5 text-white"></span>
                            </button>
                            <button type="button" x-on:click="openCheckout()" x-bind:disabled="!canCheckout"
                                class="flex flex-1 items-center justify-between gap-2 rounded-xl bg-secondary-600 px-4 py-3 text-base font-bold text-white shadow-sm hover:bg-secondary-500 focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-secondary-600 disabled:opacity-40"
                                data-checkout-button>
                                <span class="flex items-center gap-1.5">{{ __('Checkout') }} <span class="text-[10px] font-medium opacity-75">F9</span></span>
                                <span class="tabular-nums" x-text="total('total') !== null ? format(total('total')) : ''"></span>
                            </button>
                        </div>
                    </div>
                </aside>

                {{-- Phones: the cart is below the products, so keep a shortcut to it --}}
                <button type="button" x-show="lines.length" x-cloak x-on:click="$refs.cart.scrollIntoView({ behavior: 'smooth' })"
                    class="fixed inset-x-3 bottom-3 z-40 flex items-center justify-between rounded-xl bg-gray-900 px-4 py-3 text-sm font-semibold text-white shadow-lg md:hidden" data-view-cart>
                    <span class="flex items-center gap-2"><x-icon name="shopping-cart" class="size-5" /> {{ __('View cart') }} · <span x-text="itemCount"></span></span>
                    <span class="tabular-nums" x-text="total('total') !== null ? format(total('total')) : ''"></span>
                </button>
            </div>

            {{-- Toast --}}
            <div class="pointer-events-none fixed bottom-20 left-4 right-4 z-[60] flex md:bottom-4 md:right-auto" aria-live="polite">
                <template x-if="toast">
                    <div class="pointer-events-auto flex max-w-md items-start gap-2 rounded-lg px-4 py-3 text-sm font-medium shadow-lg"
                        x-bind:class="toast.type === 'error' ? 'bg-red-600 text-white' : 'bg-gray-900 text-white'" role="status">
                        <span x-text="toast.message"></span>
                    </div>
                </template>
            </div>

            {{-- Variant / modifier picker --}}
            <x-ui.modal name="pos-options" max-width="xl">
                <template x-if="picking">
                    <div>
                        <div class="mb-4 flex items-start justify-between gap-4">
                            <div>
                                <h2 class="text-lg font-semibold text-gray-900" x-text="picking.product.name"></h2>
                                <p class="text-sm text-gray-500">{{ __('Choose options, then add to cart.') }}</p>
                            </div>
                            <button type="button" x-on:click="$dispatch('close')" class="rounded-lg p-1.5 text-gray-400 hover:bg-gray-100" aria-label="{{ __('Close') }}"><x-icon name="x" class="size-5" /></button>
                        </div>

                        <template x-if="picking.product.variants.length">
                            <fieldset class="mb-5">
                                <legend class="mb-2 text-sm font-semibold text-gray-900">{{ __('Option') }} <span class="font-normal text-gray-500">· {{ __('Required') }}</span></legend>
                                <div class="grid grid-cols-2 gap-2 sm:grid-cols-3">
                                    <template x-for="variant in picking.product.variants" :key="variant.id">
                                        <button type="button" x-on:click="picking.variant_id = variant.id; picking.error = null"
                                            x-bind:class="picking.variant_id === variant.id ? 'bg-primary-600 text-white ring-primary-600' : 'bg-white text-gray-900 ring-gray-300 hover:bg-gray-50'"
                                            x-bind:aria-pressed="picking.variant_id === variant.id"
                                            class="rounded-lg px-3 py-3 text-left ring-1 ring-inset">
                                            <span class="block text-sm font-semibold" x-text="variant.name"></span>
                                            <span class="block text-xs tabular-nums opacity-80" x-text="format(variant.price)"></span>
                                        </button>
                                    </template>
                                </div>
                            </fieldset>
                        </template>

                        <template x-for="group in picking.product.modifier_groups" :key="group.id">
                            <fieldset class="mb-5">
                                <legend class="mb-2 text-sm font-semibold text-gray-900"><span x-text="group.name"></span> <span class="font-normal text-gray-500" x-text="'· ' + group.rule"></span></legend>
                                <div class="grid grid-cols-2 gap-2 sm:grid-cols-3">
                                    <template x-for="modifier in group.modifiers" :key="modifier.id">
                                        <button type="button" x-on:click="toggleModifier(group, modifier); picking.error = null"
                                            x-bind:class="picking.modifier_ids.includes(modifier.id) ? 'bg-primary-600 text-white ring-primary-600' : 'bg-white text-gray-900 ring-gray-300 hover:bg-gray-50'"
                                            x-bind:aria-pressed="picking.modifier_ids.includes(modifier.id)"
                                            class="rounded-lg px-3 py-3 text-left ring-1 ring-inset">
                                            <span class="block text-sm font-semibold" x-text="modifier.name"></span>
                                            <span class="block text-xs tabular-nums opacity-80" x-text="parseFloat(modifier.price) > 0 ? '+' + format(modifier.price) : '{{ __('No charge') }}'"></span>
                                        </button>
                                    </template>
                                </div>
                            </fieldset>
                        </template>

                        <p class="mb-3 rounded-md bg-red-50 px-3 py-2 text-sm text-red-700" x-show="picking.error" x-text="picking.error" role="alert"></p>

                        <div class="flex flex-col-reverse gap-2 border-t border-gray-100 pt-4 sm:flex-row sm:justify-end">
                            <x-ui.button variant="secondary" size="lg" x-on:click="$dispatch('close')">{{ __('Cancel') }}</x-ui.button>
                            <x-ui.button size="lg" x-on:click="confirmPick()">
                                {{ __('Add to cart') }} · <span class="tabular-nums" x-text="format(pickerUnitCents() / 100)"></span>
                            </x-ui.button>
                        </div>
                    </div>
                </template>
            </x-ui.modal>

            {{-- Discount editor (line or order) --}}
            <x-ui.modal name="pos-discount" max-width="sm">
                <template x-if="editing">
                    <form x-on:submit.prevent="applyDiscount()">
                        <h2 class="text-lg font-semibold text-gray-900" x-text="editing.target === 'order' ? '{{ __('Order discount') }}' : '{{ __('Item discount') }}: ' + (lines[editing.index]?.name ?? '')"></h2>
                        <p class="mt-1 text-sm text-gray-500" x-show="editing.target === 'order'">{{ __('Applied to the whole sale, after item discounts and before tax.') }}</p>
                        <p class="mt-1 text-sm text-gray-500" x-show="editing.target === 'line'">{{ __('A fixed amount is taken off the whole line.') }}</p>

                        <div class="mt-4 grid grid-cols-2 gap-2">
                            <button type="button" x-on:click="editing.type = 'fixed'" x-bind:class="editing.type === 'fixed' ? 'bg-primary-600 text-white' : 'bg-white text-gray-700 ring-1 ring-inset ring-gray-300'" class="rounded-lg px-3 py-2.5 text-sm font-semibold">{{ __('Fixed amount') }}</button>
                            <button type="button" x-on:click="editing.type = 'percent'" x-bind:class="editing.type === 'percent' ? 'bg-primary-600 text-white' : 'bg-white text-gray-700 ring-1 ring-inset ring-gray-300'" class="rounded-lg px-3 py-2.5 text-sm font-semibold">{{ __('Percentage (%)') }}</button>
                        </div>

                        <label class="mt-4 block">
                            <span class="text-sm font-medium text-gray-700 dark:text-gray-300" x-text="editing.type === 'percent' ? '{{ __('Percent') }}' : '{{ __('Amount') }}'"></span>
                            <input type="number" min="0" step="0.01" x-bind:max="editing.type === 'percent' ? 100 : null" x-model="editing.value" inputmode="decimal"
                                class="mt-1 block w-full rounded-lg border-gray-300 py-3 text-lg tabular-nums focus:border-primary-500 focus:ring-primary-500 dark:bg-gray-700 dark:border-gray-600 dark:text-gray-100" required>
                        </label>

                        <div class="mt-5 flex flex-col-reverse gap-2 sm:flex-row sm:justify-between">
                            <x-ui.button variant="ghost" x-on:click="applyDiscount(true)">{{ __('Remove discount') }}</x-ui.button>
                            <div class="flex flex-col-reverse gap-2 sm:flex-row">
                                <x-ui.button variant="secondary" x-on:click="$dispatch('close')">{{ __('Cancel') }}</x-ui.button>
                                <x-ui.button type="submit">{{ __('Apply') }}</x-ui.button>
                            </div>
                        </div>
                    </form>
                </template>
            </x-ui.modal>

            {{-- History --}}
            <x-ui.modal name="pos-history" :title="__('Order History')" :description="__('Today\'s completed orders. Print or edit any order.')" max-width="2xl">
                <div class="flex items-center justify-between mb-3">
                    <p class="text-xs text-gray-500" x-text="history.length + ' {{ __('orders today') }}'"></p>
                    <button type="button" x-on:click="loadHistory()" class="inline-flex items-center gap-1.5 rounded-lg px-2.5 py-1.5 text-xs font-semibold text-gray-600 hover:bg-gray-100">
                        <x-icon name="arrow-path" class="size-3.5" x-bind:class="historyLoading && 'animate-spin'" />
                        {{ __('Refresh') }}
                    </button>
                </div>

                <p x-show="historyLoading && !history.length" class="py-10 text-center text-gray-500">{{ __('Loading…') }}</p>

                <div x-show="!historyLoading && !history.length" class="py-10 text-center text-gray-400">
                    <x-icon name="orders" class="mx-auto mb-2 size-10 opacity-40" />
                    <p class="text-sm">{{ __('No completed orders today yet.') }}</p>
                </div>

                <div x-show="history.length" class="divide-y divide-gray-100 -mx-6">
                    <template x-for="sale in history" :key="sale.id">
                        <div class="flex items-center gap-3 px-6 py-3 hover:bg-gray-50">
                            {{-- Order info --}}
                            <div class="flex min-w-0 flex-1 items-center gap-3">
                                <div class="flex size-9 shrink-0 items-center justify-center rounded-lg bg-primary-100 text-primary-700 font-bold text-sm tabular-nums" x-text="'#' + sale.order_no"></div>
                                <div class="min-w-0">
                                    <p class="text-sm font-semibold text-gray-900 truncate" x-text="sale.customer_name || '{{ __('Walk-in') }}'"></p>
                                    <p class="text-xs text-gray-500" x-text="sale.order_type + ' · ' + sale.item_count + ' {{ __('items') }} · ' + sale.time"></p>
                                </div>
                            </div>

                            {{-- Total --}}
                            <span class="shrink-0 tabular-nums text-sm font-semibold text-gray-900" x-text="format(sale.total)"></span>

                            {{-- Actions --}}
                            <div class="flex shrink-0 items-center gap-1">
                                <a x-bind:href="sale.print_url" target="_blank" title="{{ __('Print') }}"
                                    class="inline-flex size-8 items-center justify-center rounded-lg text-gray-500 hover:bg-gray-100 hover:text-gray-700">
                                    <x-icon name="printer" class="size-4" />
                                </a>
                                <template x-if="canEdit">
                                    <a x-bind:href="sale.edit_url" title="{{ __('Edit order') }}"
                                        class="inline-flex size-8 items-center justify-center rounded-lg text-gray-500 hover:bg-gray-100 hover:text-gray-700">
                                        <x-icon name="pencil" class="size-4" />
                                    </a>
                                </template>
                            </div>
                        </div>
                    </template>
                </div>
            </x-ui.modal>

            {{-- Held sales --}}
            <x-ui.modal name="pos-held" :title="__('Held sales')" :description="__('Resume a held sale to continue it. Held sales are not charged until checkout.')" max-width="xl">
                <p x-show="heldLoading && !held.length" class="py-6 text-center text-gray-500">{{ __('Loading…') }}</p>
                <div x-show="!heldLoading && !held.length" class="py-8 text-center">
                    <x-icon name="history" class="mx-auto size-8 text-gray-300" />
                    <p class="mt-2 font-medium text-gray-700">{{ __('No held sales') }}</p>
                </div>
                <ul class="-my-2 divide-y divide-gray-100" x-show="held.length">
                    <template x-for="sale in held" :key="sale.id">
                        <li class="flex flex-col gap-3 py-3 sm:flex-row sm:items-center" data-held-sale>
                            <div class="min-w-0 flex-1">
                                <p class="font-semibold text-gray-900">{{ __('Held sale') }} #<span x-text="sale.id"></span> · <span class="tabular-nums" x-text="format(sale.total)"></span></p>
                                <p class="text-xs text-gray-500"><span class="font-semibold text-gray-700" x-text="sale.order_type + (sale.table ? ' · ' + sale.table : '')"></span> · <span x-text="sale.customer"></span><span x-show="sale.phone" x-text="' (' + sale.phone + ')'"></span><span x-show="sale.delivery" class="ml-1 rounded bg-sky-50 px-1.5 py-0.5 font-semibold text-sky-700">{{ __('Delivery') }}</span> · <span x-text="sale.held_at_label"></span><span x-show="sale.cashier" x-text="' · ' + sale.cashier"></span></p>
                                <p class="mt-1 text-sm text-gray-600" x-text="sale.items.map(i => i.name + ' × ' + i.quantity).join(', ')"></p>
                            </div>
                            <div class="flex shrink-0 gap-2">
                                <x-ui.button variant="ghost" size="sm" x-on:click="discardHeld(sale)">{{ __('Remove') }}</x-ui.button>
                                <x-ui.button x-on:click="resumeHeld(sale)">{{ __('Resume') }}</x-ui.button>
                            </div>
                        </li>
                    </template>
                </ul>
            </x-ui.modal>

            {{-- Clear cart confirmation --}}
            <x-ui.modal name="pos-clear" max-width="sm">
                <div class="flex gap-4">
                    <span class="flex size-10 shrink-0 items-center justify-center rounded-full bg-red-100 text-red-600"><x-icon name="alert-triangle" class="size-5" /></span>
                    <div>
                        <h2 class="text-base font-semibold text-gray-900">{{ __('Are you sure you want to clear the cart?') }}</h2>
                        <p class="mt-1 text-sm text-gray-500">{{ __('All items, discounts, customer details and the delivery fee will be removed.') }}</p>
                    </div>
                </div>
                <div class="mt-6 flex flex-col-reverse gap-2 sm:flex-row sm:justify-end">
                    <x-ui.button variant="secondary" x-on:click="$dispatch('close')">{{ __('Cancel') }}</x-ui.button>
                    <x-ui.button variant="danger" x-on:click="clearCart()" data-confirm-clear>{{ __('Clear Cart') }}</x-ui.button>
                </div>
            </x-ui.modal>

            {{-- Table picker (Dine In) --}}
            <x-ui.modal name="pos-tables" max-width="2xl">
                <div class="mb-4 flex items-start justify-between">
                    <div>
                        <h2 class="text-lg font-semibold text-gray-900">{{ __('Select table') }}</h2>
                        <p class="text-sm text-gray-500">{{ __('Reserved tables must be marked Available or Occupied before they can take an order.') }}</p>
                    </div>
                    <button type="button" x-on:click="$dispatch('close')" class="rounded-lg p-1.5 text-gray-400 hover:bg-gray-100" aria-label="{{ __('Close') }}"><x-icon name="x" class="size-5" /></button>
                </div>

                <p x-show="tablesLoading && !tables.length" class="py-6 text-center text-gray-500">{{ __('Loading…') }}</p>
                <div x-show="!tablesLoading && !tables.length" class="py-8 text-center">
                    <x-icon name="table" class="mx-auto size-8 text-gray-300" />
                    <p class="mt-2 font-medium text-gray-700">{{ __('No tables yet') }}</p>
                    <p class="text-sm text-gray-500">{{ __('Add tables under Restaurant → Tables.') }}</p>
                </div>

                <div class="grid grid-cols-2 gap-3 sm:grid-cols-3 md:grid-cols-4">
                    <template x-for="table in tables" :key="table.id">
                        <div class="flex flex-col rounded-xl ring-2"
                            x-bind:class="{
                                'ring-emerald-300 bg-emerald-50': table.status === 'available',
                                'ring-red-300 bg-red-50': table.status === 'occupied',
                                'ring-amber-300 bg-amber-50': table.status === 'reserved',
                                'outline outline-2 outline-offset-2 outline-primary-600': table.id === tableId,
                            }" data-pick-table>
                            <button type="button" x-on:click="chooseTable(table)" x-bind:disabled="table.status === 'reserved'"
                                class="flex-1 rounded-t-xl px-3 py-3 text-left disabled:cursor-not-allowed">
                                <span class="block text-lg font-bold text-gray-900" x-text="table.name"></span>
                                <span class="block text-xs font-bold uppercase tracking-wide"
                                    x-bind:class="{ 'text-emerald-700': table.status === 'available', 'text-red-700': table.status === 'occupied', 'text-amber-700': table.status === 'reserved' }"
                                    x-text="table.status_label"></span>
                                <span class="block text-xs text-gray-500">
                                    <span x-text="table.section || ''"></span><span x-show="table.section && table.capacity"> · </span><span x-show="table.capacity" x-text="table.capacity + ' {{ __('seats') }}'"></span>
                                </span>
                                <span x-show="table.open_orders" class="mt-1 block text-xs font-semibold text-red-700" x-text="table.open_orders + ' {{ __('in kitchen') }}'"></span>
                            </button>
                            <div x-show="canChangeTableStatus && table.status !== 'available'" class="border-t border-black/5 px-2 py-1.5">
                                <button type="button" x-on:click="setTableStatus(table, 'available')" class="w-full rounded-md px-2 py-1 text-xs font-semibold text-gray-700 hover:bg-white">{{ __('Mark available') }}</button>
                            </div>
                        </div>
                    </template>
                </div>
            </x-ui.modal>

            {{-- Order / item note --}}
            <x-ui.modal name="pos-note" max-width="md">
                <template x-if="noting">
                    <form x-on:submit.prevent="applyNote()">
                        <h2 class="text-lg font-semibold text-gray-900" x-text="noting.target === 'order' ? '{{ __('Order note') }}' : '{{ __('Item note') }}: ' + (lines[noting.index]?.name ?? '')"></h2>
                        <p class="mt-1 text-sm text-gray-500" x-text="noting.target === 'order' ? '{{ __('Printed on the kitchen ticket for the whole order.') }}' : '{{ __('Printed under this item on the kitchen ticket.') }}'"></p>
                        <textarea x-ref="noteText" x-model="noting.text" rows="3" x-bind:maxlength="noting.target === 'order' ? 500 : 200"
                            class="mt-3 block w-full rounded-lg border-gray-300 focus:border-primary-500 focus:ring-primary-500 dark:bg-gray-700 dark:border-gray-600 dark:text-gray-100 dark:placeholder-gray-400" data-note-text
                            x-bind:placeholder="noting.target === 'order' ? '{{ __('e.g. Customer is waiting outside') }}' : '{{ __('e.g. No onions') }}'"></textarea>
                        <div class="mt-2 flex flex-wrap gap-1.5" x-show="noting.target === 'line'">
                            @foreach ([__('No onions'), __('Extra spicy'), __('Less spicy'), __('Extra crispy'), __('No ice')] as $quick)
                                <button type="button" x-on:click="noting.text = (noting.text ? noting.text + ', ' : '') + @js($quick)" class="rounded-full bg-gray-100 px-3 py-1 text-xs font-semibold text-gray-700 hover:bg-gray-200">{{ $quick }}</button>
                            @endforeach
                        </div>
                        <div class="mt-5 flex flex-col-reverse gap-2 sm:flex-row sm:justify-between">
                            <x-ui.button variant="ghost" x-on:click="noting.text = ''; applyNote()">{{ __('Remove note') }}</x-ui.button>
                            <div class="flex flex-col-reverse gap-2 sm:flex-row">
                                <x-ui.button variant="secondary" x-on:click="$dispatch('close')">{{ __('Cancel') }}</x-ui.button>
                                <x-ui.button type="submit">{{ __('Save note') }}</x-ui.button>
                            </div>
                        </div>
                    </form>
                </template>
            </x-ui.modal>

            {{-- Customer & delivery --}}
            <x-ui.modal name="pos-customer" max-width="lg">
                <template x-if="draft">
                    <form x-on:submit.prevent="applyCustomer()" data-customer-form>
                        <div class="flex items-start justify-between">
                            <div>
                                <h2 class="text-lg font-semibold text-gray-900">{{ __('Customer & delivery') }}</h2>
                                <p class="text-sm text-gray-500">{{ __('Type the details, or search for an existing customer. Leave empty for a walk-in.') }}</p>
                            </div>
                            <button type="button" x-on:click="$dispatch('close')" class="rounded-lg p-1.5 text-gray-400 hover:bg-gray-100" aria-label="{{ __('Close') }}"><x-icon name="x" class="size-5" /></button>
                        </div>

                        <label class="relative mt-4 block">
                            <span class="sr-only">{{ __('Search existing customers') }}</span>
                            <x-icon name="search" class="pointer-events-none absolute left-3 top-1/2 size-4 -translate-y-1/2 text-gray-400" />
                            <input x-model="draft.query" type="search" placeholder="{{ __('Search existing customers by name or phone') }}"
                                class="block w-full rounded-lg border-gray-300 pl-9 text-sm focus:border-primary-500 focus:ring-primary-500 dark:bg-gray-700 dark:border-gray-600 dark:text-gray-100 dark:placeholder-gray-400">
                        </label>

                        <ul x-show="draftMatches.length" class="mt-2 divide-y divide-gray-100 overflow-hidden rounded-lg ring-1 ring-gray-200">
                            <template x-for="c in draftMatches" :key="c.id">
                                <li>
                                    <button type="button" x-on:click="draftPick(c)" class="flex w-full items-center justify-between gap-2 px-3 py-2 text-left text-sm hover:bg-primary-50">
                                        <span class="min-w-0">
                                            <span class="block truncate font-medium text-gray-900" x-text="c.name"></span>
                                            <span class="block truncate text-xs text-gray-500" x-text="[c.phone, c.address].filter(Boolean).join(' · ')"></span>
                                        </span>
                                        <span class="shrink-0 text-xs font-semibold text-primary-700">{{ __('Use') }}</span>
                                    </button>
                                </li>
                            </template>
                        </ul>

                        <div x-show="draft.customerId" class="mt-3 flex items-center justify-between rounded-lg bg-primary-50 px-3 py-2 text-sm text-primary-800">
                            <span>{{ __('Linked to an existing customer.') }}</span>
                            <button type="button" x-on:click="draftUnlink()" class="font-semibold hover:underline">{{ __('Unlink') }}</button>
                        </div>

                        <div class="mt-4 grid gap-3 sm:grid-cols-2">
                            <label class="block">
                                <span class="text-sm font-medium text-gray-700 dark:text-gray-300">{{ __('Customer name') }}</span>
                                <input x-ref="customerName" x-model="draft.name" type="text" maxlength="150" autocomplete="off"
                                    class="mt-1 block w-full rounded-lg border-gray-300 py-2.5 focus:border-primary-500 focus:ring-primary-500 dark:bg-gray-700 dark:border-gray-600 dark:text-gray-100" data-customer-name>
                            </label>
                            <label class="block">
                                <span class="text-sm font-medium text-gray-700 dark:text-gray-300">{{ __('Phone number') }}</span>
                                <input x-model="draft.phone" type="tel" maxlength="30" autocomplete="off" inputmode="tel"
                                    class="mt-1 block w-full rounded-lg border-gray-300 py-2.5 focus:border-primary-500 focus:ring-primary-500 dark:bg-gray-700 dark:border-gray-600 dark:text-gray-100" data-customer-phone>
                            </label>
                            <label class="block sm:col-span-2">
                                <span class="text-sm font-medium text-gray-700 dark:text-gray-300">{{ __('Address') }}</span>
                                <textarea x-model="draft.address" rows="2" maxlength="500"
                                    class="mt-1 block w-full rounded-lg border-gray-300 focus:border-primary-500 focus:ring-primary-500 dark:bg-gray-700 dark:border-gray-600 dark:text-gray-100" data-customer-address></textarea>
                            </label>
                            <label class="block" x-show="orderType === 'delivery'">
                                <span class="text-sm font-medium text-gray-700 dark:text-gray-300">{{ __('Delivery fee') }}</span>
                                <input x-model="draft.deliveryFee" type="number" min="0" step="0.01" inputmode="decimal" placeholder="0.00"
                                    class="mt-1 block w-full rounded-lg border-gray-300 py-2.5 tabular-nums focus:border-primary-500 focus:ring-primary-500 dark:bg-gray-700 dark:border-gray-600 dark:text-gray-100" data-delivery-fee>
                                <span class="mt-1 block text-xs text-gray-500">{{ __('Added to the total. Not taxed or discounted.') }}</span>
                            </label>
                            <label class="flex items-start gap-2 self-center" x-show="canSaveCustomer && !draft.customerId && draft.name.trim()">
                                <input type="checkbox" x-model="draft.save" class="mt-0.5 rounded border-gray-300 text-primary-600 focus:ring-primary-500 dark:border-gray-600" data-save-customer>
                                <span class="text-sm text-gray-700 dark:text-gray-300">{{ __('Save as a new customer') }}</span>
                            </label>
                        </div>

                        <div class="mt-5 flex flex-col-reverse gap-2 sm:flex-row sm:justify-between">
                            <x-ui.button variant="ghost" x-on:click="walkIn()">{{ __('Walk-in (clear)') }}</x-ui.button>
                            <div class="flex flex-col-reverse gap-2 sm:flex-row">
                                <x-ui.button variant="secondary" x-on:click="$dispatch('close')">{{ __('Cancel') }}</x-ui.button>
                                <x-ui.button type="submit">{{ __('Done') }}</x-ui.button>
                            </div>
                        </div>
                    </form>
                </template>
            </x-ui.modal>

            {{-- Checkout --}}
            <x-ui.modal name="pos-checkout" max-width="lg">
                <form x-on:submit.prevent="submitCheckout()" x-show="quote">
                    <div class="flex items-start justify-between">
                        <h2 class="text-lg font-semibold text-gray-900">{{ __('Payment') }}</h2>
                        <button type="button" x-on:click="$dispatch('close')" class="rounded-lg p-1.5 text-gray-400 hover:bg-gray-100" aria-label="{{ __('Close') }}"><x-icon name="x" class="size-5" /></button>
                    </div>

                    <div class="mt-3 rounded-xl bg-gray-900 px-5 py-4 text-white">
                        <p class="text-xs font-semibold uppercase tracking-wide text-gray-400">{{ __('Total') }}</p>
                        <p class="text-3xl font-bold tabular-nums" x-text="format(quote?.total)"></p>
                        <p class="mt-1 text-xs text-gray-400"><span x-text="({dine_in: '{{ __('Dine In') }}', take_away: '{{ __('Take Away') }}', delivery: '{{ __('Delivery') }}'})[orderType]"></span><span x-show="tableName && orderType === 'dine_in'" x-text="' · ' + tableName"></span> · <span x-text="itemCount"></span> {{ __('items') }} · <span x-text="customerName"></span><span x-show="deliveryFee" x-text="' · {{ __('Delivery') }} ' + format(deliveryFee)"></span></p>
                    </div>

                    <div class="mt-4 grid grid-cols-3 gap-2" role="radiogroup" aria-label="{{ __('Payment method') }}">
                        @foreach (['cash' => ['Cash', 'banknote'], 'card' => ['Card', 'credit-card'], 'easypaisa' => ['Easypaisa', 'device-mobile'], 'jazzcash' => ['JazzCash', 'device-mobile'], 'mixed' => ['Mixed', 'plus']] as $method => [$label, $icon])
                            <button type="button" role="radio" x-on:click="setMethod('{{ $method }}')"
                                x-bind:aria-checked="pay.method === '{{ $method }}'"
                                x-bind:class="pay.method === '{{ $method }}' ? 'bg-primary-600 text-white ring-primary-600' : 'bg-white text-gray-700 ring-gray-300 hover:bg-gray-50'"
                                class="flex flex-col items-center gap-1 rounded-xl px-3 py-3 text-sm font-bold uppercase ring-1 ring-inset">
                                <x-icon :name="$icon" class="size-5" /> {{ __($label) }}
                            </button>
                        @endforeach
                    </div>

                    {{-- Cash --}}
                    <div x-show="pay.method === 'cash'" class="mt-4">
                        <label class="block">
                            <span class="text-sm font-medium text-gray-700 dark:text-gray-300">{{ __('Cash received') }}</span>
                            <input x-ref="cashReceived" type="number" min="0" step="0.01" inputmode="decimal" x-model="pay.cash_received"
                                class="mt-1 block w-full rounded-lg border-gray-300 py-3 text-2xl font-semibold tabular-nums focus:border-primary-500 focus:ring-primary-500 dark:bg-gray-700 dark:border-gray-600 dark:text-gray-100" data-cash-received>
                        </label>
                        <div class="mt-2 flex flex-wrap gap-2">
                            <template x-for="amount in quickCash" :key="amount">
                                <button type="button" x-on:click="pay.cash_received = amount" class="rounded-lg bg-gray-100 px-3 py-2 text-sm font-semibold tabular-nums text-gray-700 hover:bg-gray-200" x-text="format(amount)"></button>
                            </template>
                        </div>
                        <div class="mt-3 flex items-baseline justify-between rounded-lg px-4 py-3"
                            x-bind:class="parseFloat(change) >= 0 ? 'bg-emerald-50 text-emerald-800' : 'bg-gray-50 text-gray-500'">
                            <span class="text-sm font-semibold">{{ __('Change') }}</span>
                            <span class="text-2xl font-bold tabular-nums" x-text="parseFloat(change) >= 0 && pay.cash_received !== '' ? format(change) : '—'" data-change></span>
                        </div>
                    </div>

                    {{-- Card --}}
                    <div x-show="pay.method === 'card'" x-cloak class="mt-4">
                        <label class="block">
                            <span class="text-sm font-medium text-gray-700 dark:text-gray-300">{{ __('Card amount') }}</span>
                            <input type="number" min="0" step="0.01" inputmode="decimal" x-model="pay.card_amount" readonly
                                class="mt-1 block w-full rounded-lg border-gray-300 bg-gray-50 py-3 text-2xl font-semibold tabular-nums dark:border-gray-600 dark:bg-gray-800 dark:text-gray-100">
                        </label>
                        <p class="mt-2 text-xs text-gray-500">{{ __('Charge this amount on the card terminal. No card details are stored.') }}</p>
                    </div>

                    {{-- Easypaisa --}}
                    <div x-show="pay.method === 'easypaisa'" x-cloak class="mt-4">
                        <label class="block">
                            <span class="text-sm font-medium text-gray-700 dark:text-gray-300">{{ __('Easypaisa amount') }}</span>
                            <input type="number" min="0" step="0.01" inputmode="decimal" x-model="pay.card_amount" readonly
                                class="mt-1 block w-full rounded-lg border-gray-300 bg-gray-50 py-3 text-2xl font-semibold tabular-nums dark:border-gray-600 dark:bg-gray-800 dark:text-gray-100">
                        </label>
                        <p class="mt-2 text-xs text-gray-500">{{ __('Collect this amount via Easypaisa. No account details are stored.') }}</p>
                    </div>

                    {{-- JazzCash --}}
                    <div x-show="pay.method === 'jazzcash'" x-cloak class="mt-4">
                        <label class="block">
                            <span class="text-sm font-medium text-gray-700 dark:text-gray-300">{{ __('JazzCash amount') }}</span>
                            <input type="number" min="0" step="0.01" inputmode="decimal" x-model="pay.card_amount" readonly
                                class="mt-1 block w-full rounded-lg border-gray-300 bg-gray-50 py-3 text-2xl font-semibold tabular-nums dark:border-gray-600 dark:bg-gray-800 dark:text-gray-100">
                        </label>
                        <p class="mt-2 text-xs text-gray-500">{{ __('Collect this amount via JazzCash. No account details are stored.') }}</p>
                    </div>

                    {{-- Mixed --}}
                    <div x-show="pay.method === 'mixed'" x-cloak class="mt-4 grid grid-cols-2 gap-3">
                        <label class="block">
                            <span class="text-sm font-medium text-gray-700 dark:text-gray-300">{{ __('Cash') }}</span>
                            <input x-ref="cashAmount" type="number" min="0" step="0.01" inputmode="decimal" x-model="pay.cash_amount" x-on:input="fillCard()"
                                class="mt-1 block w-full rounded-lg border-gray-300 py-3 text-xl font-semibold tabular-nums focus:border-primary-500 focus:ring-primary-500 dark:bg-gray-700 dark:border-gray-600 dark:text-gray-100">
                        </label>
                        <label class="block">
                            <span class="text-sm font-medium text-gray-700 dark:text-gray-300">{{ __('Card') }}</span>
                            <input type="number" min="0" step="0.01" inputmode="decimal" x-model="pay.card_amount"
                                class="mt-1 block w-full rounded-lg border-gray-300 py-3 text-xl font-semibold tabular-nums focus:border-primary-500 focus:ring-primary-500 dark:bg-gray-700 dark:border-gray-600 dark:text-gray-100">
                        </label>
                        <p class="col-span-2 text-xs text-gray-500">{{ __('The card amount fills in automatically with the rest of the total.') }}</p>
                    </div>

                    <p class="mt-3 text-sm text-amber-700" x-show="payProblem && !Object.keys(pay.errors).length" x-text="payProblem"></p>
                    <p class="mt-3 rounded-md bg-red-50 px-3 py-2 text-sm text-red-700" x-show="Object.keys(pay.errors).length" x-text="firstError(pay.errors)" role="alert"></p>

                    <div class="mt-5 flex flex-col-reverse gap-2 sm:flex-row sm:justify-end">
                        <x-ui.button variant="secondary" size="lg" x-on:click="$dispatch('close')">{{ __('Cancel') }}</x-ui.button>
                        <x-ui.button type="submit" variant="success" size="lg" x-bind:disabled="!!payProblem || pay.submitting" data-complete-sale>
                            <span x-text="pay.submitting ? '{{ __('Saving…') }}' : '{{ __('Complete sale') }}'"></span>
                        </x-ui.button>
                    </div>
                </form>
            </x-ui.modal>

            {{-- Receipt --}}
            <x-ui.modal name="pos-receipt" max-width="md">
                <template x-if="receipt">
                    <div>
                        <div class="mb-4 flex items-center gap-3 rounded-lg bg-emerald-50 px-4 py-3 text-emerald-800">
                            <x-icon name="check-circle" class="size-6" />
                            <div>
                                <p class="font-semibold">{{ __('Sale complete') }} · <span x-text="receipt.invoice"></span></p>
                                <p class="text-sm" x-show="parseFloat(receipt.change) > 0">{{ __('Change due') }}: <span class="font-bold tabular-nums" x-text="format(receipt.change)"></span></p>
                            </div>
                        </div>
                        <div class="mb-4 rounded-lg bg-orange-50 px-4 py-3 text-sm text-orange-900 ring-1 ring-orange-200" x-show="receipt.kots.length" data-kot-sent>
                            <p class="font-semibold">
                                <span x-text="receipt.orderType"></span><span x-show="receipt.table" x-text="' · ' + receipt.table"></span> · {{ __('Sent to kitchen') }}
                            </p>
                            <template x-for="kot in receipt.kots" :key="kot.kot_number">
                                <p class="mt-1"><span class="font-bold" x-text="kot.kot_number"></span> · <span x-text="kot.items + ' {{ __('items') }}'"></span> · {{ __('Pending') }}</p>
                            </template>
                        </div>

                        <p class="mb-3 flex items-center gap-2 rounded-lg bg-gray-50 px-3 py-2 text-sm text-gray-700" x-show="receipt.printed" data-print-status>
                            <x-icon name="printer" class="size-4 text-gray-500" />
                            <span x-text="receipt.printed === 'receipt' ? '{{ __('Printing the customer bill…') }}'
                                : receipt.printed === 'kitchen' ? '{{ __('Printing the kitchen bill (KOT)…') }}'
                                : (receipt.kots.length ? '{{ __('Printing 2 bills: customer bill + kitchen bill (KOT)…') }}' : '{{ __('Printing the customer bill…') }}')"></span>
                        </p>

                        <div class="max-h-[45vh] overflow-y-auto rounded-lg border border-gray-200 p-4" x-html="receipt.html"></div>

                        <div class="mt-4 grid gap-2" x-bind:class="receipt.kots.length ? 'grid-cols-3' : 'grid-cols-1'">
                            <x-ui.button variant="secondary" x-on:click="printOrder()" data-print-both>
                                <x-icon name="printer" class="size-4" /> <span x-text="receipt.kots.length ? '{{ __('Print both') }}' : '{{ __('Print bill') }}'"></span>
                            </x-ui.button>
                            <x-ui.button variant="secondary" x-show="receipt.kots.length" x-on:click="printOrder('receipt')">{{ __('Customer bill') }}</x-ui.button>
                            <x-ui.button variant="secondary" x-show="receipt.kots.length" x-on:click="printOrder('kitchen')">{{ __('Kitchen bill') }}</x-ui.button>
                        </div>
                        <x-ui.button size="lg" class="mt-2 w-full" x-on:click="newSale()">{{ __('New sale') }}</x-ui.button>
                    </div>
                </template>
            </x-ui.modal>

            {{-- Hidden frame the bills are printed from (see printOrder()). --}}
            <iframe x-ref="printFrame" title="{{ __('Print') }}" aria-hidden="true" tabindex="-1"
                style="position: fixed; right: 0; bottom: 0; width: 0; height: 0; border: 0; visibility: hidden;" data-print-frame></iframe>
        </div>
    </body>
</html>
