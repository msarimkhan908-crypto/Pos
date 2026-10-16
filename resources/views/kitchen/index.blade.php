@php
    $config = [
        'urls' => [
            'orders' => route('kitchen.orders'),
            'status' => route('kitchen.orders.status', ['kitchenOrder' => '__ID__']),
        ],
        'canManage' => auth()->user()->can('kitchen.manage'),
        'messages' => [
            'start' => __('Start preparing'),
            'ready' => __('Mark ready'),
            'complete' => __('Complete'),
            'failed' => __('Could not reach the server. Retrying…'),
            'session' => __('Your session has expired. Reload the page and sign in again.'),
        ],
    ];
    $filters = [
        'all' => __('All'),
        'pending' => __('Pending'),
        'preparing' => __('Preparing'),
        'ready' => __('Ready'),
        'completed' => __('Completed'),
    ];
@endphp
<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="h-full">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="csrf-token" content="{{ csrf_token() }}">
        <title>{{ __('Kitchen Display') }} · {{ config('app.name', 'Laravel') }}</title>
        <link rel="preconnect" href="https://fonts.bunny.net">
        <link href="https://fonts.bunny.net/css?family=figtree:400,500,600,700&display=swap" rel="stylesheet" />
        @vite(['resources/css/app.css', 'resources/js/app.js'])
        <x-theme />
    </head>
    <body class="h-full bg-gray-100 font-sans text-gray-900 antialiased">
        <div x-data="kitchen(@js($config))" class="flex min-h-full flex-col" data-kitchen>
            <header class="sticky top-0 z-10 border-b border-gray-200 bg-white">
                <div class="flex h-14 items-center gap-3 px-4">
                    <a href="{{ route('dashboard') }}" class="inline-flex items-center gap-1.5 rounded-lg px-2 py-1.5 text-sm font-medium text-gray-600 hover:bg-gray-100">
                        <x-icon name="arrow-left" class="size-4" /><span class="hidden sm:inline">{{ __('Dashboard') }}</span>
                    </a>
                    <div class="flex items-center gap-2">
                        <span class="flex size-8 items-center justify-center rounded-lg bg-orange-500 text-white"><x-icon name="kitchen" class="size-4" /></span>
                        <h1 class="text-base font-semibold">{{ __('Kitchen Display') }}</h1>
                    </div>
                    <p class="ml-auto text-xs text-gray-500" aria-live="polite">
                        <span x-show="error" x-cloak class="font-semibold text-red-600" x-text="error"></span>
                        <span x-show="!error && lastUpdated" x-text="'{{ __('Updated') }} ' + (lastUpdated ? lastUpdated.toLocaleTimeString([], { hour: '2-digit', minute: '2-digit', second: '2-digit' }) : '')"></span>
                    </p>
                </div>

                {{-- Status filter --}}
                <nav class="flex gap-2 overflow-x-auto px-4 pb-2" aria-label="{{ __('Status') }}">
                    @foreach ($filters as $value => $label)
                        <button type="button" x-on:click="setStatus('{{ $value }}')"
                            x-bind:class="status === '{{ $value }}' ? 'bg-gray-900 text-white' : 'bg-gray-100 text-gray-700 hover:bg-gray-200'"
                            x-bind:aria-pressed="status === '{{ $value }}'"
                            class="inline-flex shrink-0 items-center gap-2 rounded-full px-4 py-2 text-sm font-semibold" data-filter="{{ $value }}">
                            {{ $label }}
                            <span class="rounded-full bg-white/20 px-1.5 text-xs tabular-nums" x-text="counts['{{ $value }}'] ?? 0"></span>
                        </button>
                    @endforeach
                </nav>

                {{-- Station filter --}}
                @if ($stations->isNotEmpty())
                    <nav class="flex gap-2 overflow-x-auto border-t border-gray-100 px-4 py-2" aria-label="{{ __('Station') }}">
                        <button type="button" x-on:click="setStation(null)"
                            x-bind:class="station === null ? 'bg-orange-500 text-white' : 'bg-orange-50 text-orange-800 hover:bg-orange-100'"
                            class="shrink-0 rounded-full px-4 py-1.5 text-sm font-semibold">{{ __('All stations') }}</button>
                        @foreach ($stations as $station)
                            <button type="button" x-on:click="setStation({{ $station->id }})"
                                x-bind:class="station === {{ $station->id }} ? 'bg-orange-500 text-white' : 'bg-orange-50 text-orange-800 hover:bg-orange-100'"
                                class="shrink-0 whitespace-nowrap rounded-full px-4 py-1.5 text-sm font-semibold" data-station="{{ $station->id }}">{{ $station->name }}</button>
                        @endforeach
                    </nav>
                @endif
            </header>

            <main class="flex-1 p-4">
                <p x-show="loading" class="py-16 text-center text-gray-500">{{ __('Loading…') }}</p>

                <div x-show="!loading && orders.length === 0" x-cloak class="py-20 text-center">
                    <x-icon name="kitchen" class="mx-auto size-10 text-gray-300" />
                    <p class="mt-2 text-lg font-semibold text-gray-700">{{ __('No kitchen orders') }}</p>
                    <p class="text-sm text-gray-500">{{ __('New orders from the POS appear here automatically.') }}</p>
                </div>

                <div class="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-3 2xl:grid-cols-4">
                    <template x-for="order in orders" :key="order.id">
                        <article class="flex flex-col overflow-hidden rounded-xl bg-white shadow-sm ring-1 ring-gray-200" data-kot-card>
                            <header class="flex items-start justify-between gap-3 px-4 py-3"
                                x-bind:class="{
                                    'bg-sky-600 text-white': order.status === 'pending',
                                    'bg-amber-400 text-gray-900': order.status === 'preparing',
                                    'bg-emerald-600 text-white': order.status === 'ready',
                                    'bg-gray-200 text-gray-700': order.status === 'completed',
                                }">
                                <div>
                                    <p class="text-xl font-extrabold tracking-tight" x-text="order.kot_number"></p>
                                    <p class="text-sm font-semibold">
                                        <span x-show="order.table" x-text="order.table"></span>
                                        <span x-show="order.table"> · </span>
                                        <span x-text="order.order_type_label"></span>
                                    </p>
                                </div>
                                <div class="text-right">
                                    <p class="rounded-md bg-white/90 px-2 py-0.5 text-xs font-bold uppercase tracking-wide text-gray-900" x-text="order.status_label" data-kot-status></p>
                                    <p class="mt-1 text-xs font-semibold opacity-90" x-text="order.time"></p>
                                </div>
                            </header>

                            <div class="flex items-center justify-between border-b border-gray-100 px-4 py-1.5 text-xs">
                                <span class="text-gray-500"><span x-text="order.invoice"></span><span x-show="order.customer && !order.table" x-text="' · ' + order.customer"></span></span>
                                <span class="font-semibold tabular-nums" x-bind:class="ageClass(order)" x-text="minutes(order) + ' {{ __('min') }}'"></span>
                            </div>

                            <ul class="flex-1 divide-y divide-gray-100 px-4">
                                <template x-for="item in order.items" :key="item.id">
                                    <li class="py-2" x-bind:class="item.status === 'completed' && 'opacity-50'">
                                        <div class="flex items-start gap-2">
                                            <span class="min-w-[2.25rem] rounded-md bg-gray-900 px-1.5 py-0.5 text-center text-base font-extrabold tabular-nums text-white" x-text="item.quantity + '×'"></span>
                                            <div class="min-w-0 flex-1">
                                                <p class="text-base font-bold leading-tight">
                                                    <span x-text="item.name"></span>
                                                    <span x-show="item.variant" class="font-semibold text-gray-600" x-text="'(' + item.variant + ')'"></span>
                                                </p>
                                                <template x-for="modifier in item.modifiers">
                                                    <p class="text-sm text-gray-600" x-text="'+ ' + modifier"></p>
                                                </template>
                                                <p x-show="item.notes" class="mt-0.5 rounded bg-yellow-100 px-1.5 py-0.5 text-sm font-bold text-yellow-900" x-text="'» ' + item.notes"></p>
                                                <p x-show="station === null" class="mt-0.5 text-[11px] font-semibold uppercase tracking-wide text-orange-700">
                                                    <span x-text="item.station"></span> · <span x-text="item.status_label"></span>
                                                </p>
                                            </div>
                                        </div>
                                    </li>
                                </template>
                            </ul>

                            <div x-show="order.notes" class="mx-4 mb-3 rounded-lg bg-red-50 px-3 py-2 text-sm text-red-900 ring-1 ring-red-200">
                                <p class="text-[11px] font-bold uppercase tracking-wide">{{ __('Note') }}</p>
                                <p class="whitespace-pre-line font-semibold" x-text="order.notes"></p>
                            </div>

                            <footer class="flex gap-2 border-t border-gray-100 p-3">
                                <a x-bind:href="order.print_url" target="_blank" rel="noopener"
                                    class="inline-flex items-center justify-center rounded-lg px-3 py-3 text-gray-600 ring-1 ring-inset ring-gray-300 hover:bg-gray-50" aria-label="{{ __('Print KOT') }}" title="{{ __('Print KOT') }}">
                                    <x-icon name="printer" class="size-5" />
                                </a>
                                <template x-if="canManage && next(order)">
                                    <button type="button" x-on:click="advance(order, next(order).status)" x-bind:disabled="!!busy[order.id]"
                                        class="flex-1 rounded-lg px-4 py-3 text-base font-bold uppercase tracking-wide text-white disabled:opacity-50"
                                        x-bind:class="{
                                            'bg-sky-600 hover:bg-sky-500': order.status === 'pending',
                                            'bg-emerald-600 hover:bg-emerald-500': order.status === 'preparing',
                                            'bg-gray-900 hover:bg-gray-700': order.status === 'ready',
                                        }"
                                        x-text="next(order).label" data-kot-action></button>
                                </template>
                                <p x-show="order.status === 'completed'" class="flex flex-1 items-center justify-center gap-1 text-sm font-semibold text-gray-500">
                                    <x-icon name="check" class="size-4" /> {{ __('Completed') }}
                                </p>
                            </footer>
                        </article>
                    </template>
                </div>
            </main>

            <div class="pointer-events-none fixed bottom-4 left-4 right-4 z-50 flex md:right-auto" aria-live="polite">
                <template x-if="toast">
                    <div class="pointer-events-auto rounded-lg px-4 py-3 text-sm font-medium text-white shadow-lg" x-bind:class="toast.type === 'error' ? 'bg-red-600' : 'bg-gray-900'" x-text="toast.message"></div>
                </template>
            </div>
        </div>
    </body>
</html>
