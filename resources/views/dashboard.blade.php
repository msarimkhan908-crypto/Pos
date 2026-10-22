@use('Illuminate\Support\Facades\Route')
@use('Illuminate\Support\Number')
@use('App\Support\Money')
@use('App\Support\Format')

@php
    $statusColors = ['completed' => 'success', 'paid' => 'success', 'pending' => 'warning', 'cancelled' => 'danger', 'refunded' => 'danger'];
    $hasWidgets = $stats !== [] || $salesOverview || $recentOrders || $topProducts || $recentPurchases || $dailyReport;
@endphp

<x-app-layout>
    <x-slot name="title">{{ __('Dashboard') }}</x-slot>

    <x-ui.page-header
        :title="__('Dashboard')"
        :description="__('Welcome back, :name. Here is an overview of your store.', ['name' => auth()->user()->name])"
    >
        <x-slot:actions>
            {{-- Date navigator --}}
            <div class="inline-flex items-center divide-x divide-gray-200 overflow-hidden rounded-lg border border-gray-200 bg-white shadow-sm dark:divide-gray-700 dark:border-gray-700 dark:bg-gray-800">
                {{-- Previous day --}}
                <a
                    href="{{ route('dashboard', array_merge(request()->except('date'), ['date' => $date->subDay()->toDateString()])) }}"
                    class="flex size-9 items-center justify-center text-gray-500 transition hover:bg-gray-50 hover:text-gray-700 dark:text-gray-400 dark:hover:bg-gray-700 dark:hover:text-gray-200"
                    aria-label="{{ __('Previous day') }}"
                    title="{{ __('Previous day') }}"
                >
                    <x-icon name="arrow-left" class="size-4" />
                </a>

                {{-- Date label / picker --}}
                <div x-data class="relative">
                    <button
                        type="button"
                        class="flex items-center gap-2 px-3 py-2 text-sm text-gray-700 transition hover:bg-gray-50 dark:text-gray-300 dark:hover:bg-gray-700"
                        @click="$refs.dp.showPicker ? $refs.dp.showPicker() : $refs.dp.click()"
                        :title="'{{ __('Pick a date') }}'"
                    >
                        <x-icon name="calendar" class="size-4 text-gray-400 dark:text-gray-500" />
                        <time datetime="{{ $date->toDateString() }}">
                            @if ($date->isToday())
                                {{ __('Today') }} · {{ $date->isoFormat('D MMM YYYY') }}
                            @elseif ($date->isYesterday())
                                {{ __('Yesterday') }} · {{ $date->isoFormat('D MMM YYYY') }}
                            @else
                                {{ $date->isoFormat('ddd, D MMM YYYY') }}
                            @endif
                        </time>
                    </button>
                    <input
                        x-ref="dp"
                        type="date"
                        value="{{ $date->toDateString() }}"
                        max="{{ today()->toDateString() }}"
                        onchange="window.location.href='{{ route('dashboard', array_merge(request()->except('date'), ['date' => '__DATE__'])) }}'.replace('__DATE__', this.value)"
                        class="sr-only"
                        aria-label="{{ __('Go to date') }}"
                    >
                </div>

                {{-- Next day (disabled on today) --}}
                @if ($date->isToday())
                    <span class="flex size-9 cursor-not-allowed items-center justify-center text-gray-300 dark:text-gray-600" aria-disabled="true" title="{{ __('No future data') }}">
                        <x-icon name="arrow-right" class="size-4" />
                    </span>
                @else
                    <a
                        href="{{ route('dashboard', array_merge(request()->except('date'), ['date' => $date->addDay()->toDateString()])) }}"
                        class="flex size-9 items-center justify-center text-gray-500 transition hover:bg-gray-50 hover:text-gray-700 dark:text-gray-400 dark:hover:bg-gray-700 dark:hover:text-gray-200"
                        aria-label="{{ __('Next day') }}"
                        title="{{ __('Next day') }}"
                    >
                        <x-icon name="arrow-right" class="size-4" />
                    </a>
                @endif
            </div>

            @if (! $date->isToday())
                <a
                    href="{{ route('dashboard', request()->except('date')) }}"
                    class="inline-flex items-center gap-1.5 rounded-lg border border-gray-200 bg-white px-3 py-2 text-xs font-medium text-gray-600 transition hover:bg-gray-50 dark:border-gray-700 dark:bg-gray-800 dark:text-gray-400 dark:hover:bg-gray-700"
                >
                    {{ __('Back to Today') }}
                </a>
            @endif

            @if (Route::has('pos.index') && auth()->user()->can('sales.create'))
                <x-ui.button :href="route('pos.index')" icon="pos">{{ __('New Sale') }}</x-ui.button>
            @endif
        </x-slot:actions>
    </x-ui.page-header>

    @if (! $hasWidgets)
        <x-ui.card>
            <x-ui.empty-state
                icon="roles"
                :title="__('Nothing to show yet')"
                :description="auth()->user()->roles->isEmpty()
                    ? __('No role has been assigned to your account yet. Ask an administrator for access.')
                    : __('All dashboard widgets you can see are switched off in Settings → General Settings → Dashboard.')"
            />
        </x-ui.card>
    @else
        {{-- Statistics --}}
        @if ($stats !== [])
            <div class="grid grid-cols-1 gap-4 sm:grid-cols-2 xl:grid-cols-3">
                @foreach ($stats as $stat)
                    <x-dashboard.stat-card
                        :label="$stat['label']"
                        :value="$stat['value']"
                        :icon="$stat['icon']"
                        :description="$stat['description']"
                        :tone="$stat['tone']"
                    />
                @endforeach
            </div>
        @endif

        <div class="mt-6 grid grid-cols-1 gap-6 xl:grid-cols-3">
            {{-- Sales overview --}}
            @if ($salesOverview)
                <x-ui.card class="xl:col-span-2" :title="__('Sales Overview')" :description="$date->isToday() ? ($range === 1 ? __('Sales for today') : __('Daily sales for the last :days days', ['days' => $range])) : ($range === 1 ? __('Sales for :date', ['date' => $date->isoFormat('D MMM YYYY')]) : __(':days days ending :date', ['days' => $range, 'date' => $date->isoFormat('D MMM YYYY')]))">
                    <x-slot:actions>
                        <div class="inline-flex rounded-lg bg-gray-100 p-0.5 text-xs font-medium" role="group" aria-label="{{ __('Date range') }}">
                            @foreach ($ranges as $option)
                                <a
                                    href="{{ route('dashboard', ['range' => $option]) }}"
                                    @if ($option === $range) aria-current="true" @endif
                                    @class([
                                        'rounded-md px-2.5 py-1 transition',
                                        'bg-white text-gray-900 shadow-sm' => $option === $range,
                                        'text-gray-500 hover:text-gray-700' => $option !== $range,
                                    ])
                                >{{ $option === 1 ? __('Today') : __(':days days', ['days' => $option]) }}</a>
                            @endforeach
                        </div>
                    </x-slot:actions>

                    <dl class="mb-6 grid grid-cols-3 gap-4 border-b border-gray-100 pb-5">
                        <div>
                            <dt class="text-xs font-medium text-gray-500">{{ __('Total sales') }}</dt>
                            <dd class="mt-1 text-lg font-semibold tabular-nums text-gray-900">{{ Money::format($salesOverview['total']) }}</dd>
                        </div>
                        <div>
                            <dt class="text-xs font-medium text-gray-500">{{ __('Orders') }}</dt>
                            <dd class="mt-1 text-lg font-semibold tabular-nums text-gray-900">{{ Number::format($salesOverview['orders']) }}</dd>
                        </div>
                        <div>
                            <dt class="text-xs font-medium text-gray-500">{{ __('Avg. order') }}</dt>
                            <dd class="mt-1 text-lg font-semibold tabular-nums text-gray-900">{{ Money::format($salesOverview['average_order']) }}</dd>
                        </div>
                    </dl>

                    <x-dashboard.sales-chart :days="$salesOverview['days']" :currency="Money::currency()" />
                </x-ui.card>
            @endif

            {{-- Top selling products --}}
            @if ($topProducts)
                <x-ui.card :title="__('Top Selling Products')" :padding="false">
                    <x-slot:actions>
                        <div class="inline-flex rounded-lg bg-gray-100 p-0.5 text-xs font-medium dark:bg-gray-700" role="group">
                            @foreach ($ranges as $option)
                                <a
                                    href="{{ route('dashboard', array_merge(request()->query(), ['top_range' => $option])) }}"
                                    @if ($option === $topRange) aria-current="true" @endif
                                    @class([
                                        'rounded-md px-2.5 py-1 transition',
                                        'bg-white text-gray-900 shadow-sm dark:bg-gray-600 dark:text-white' => $option === $topRange,
                                        'text-gray-500 hover:text-gray-700 dark:text-gray-400 dark:hover:text-gray-200' => $option !== $topRange,
                                    ])
                                >{{ $option === 1 ? __('Today') : __(':days days', ['days' => $option]) }}</a>
                            @endforeach
                        </div>
                    </x-slot:actions>
                    @forelse ($topProducts as $index => $product)
                        <div class="flex items-center gap-3 border-b border-gray-100 px-5 py-3 last:border-b-0">
                            <span class="flex size-7 shrink-0 items-center justify-center rounded-full bg-gray-100 text-xs font-semibold text-gray-600">{{ $index + 1 }}</span>
                            <div class="min-w-0 flex-1">
                                <p class="truncate text-sm font-medium text-gray-900">{{ data_get($product, 'name') }}</p>
                                <p class="text-xs text-gray-500">{{ trans_choice(':count sold|:count sold', data_get($product, 'quantity', 0)) }}</p>
                            </div>
                            <span class="text-sm font-medium tabular-nums text-gray-900">{{ Money::format(data_get($product, 'revenue', 0)) }}</span>
                        </div>
                    @empty
                        <x-ui.empty-state
                            icon="products"
                            :title="__('No product sales yet')"
                            :description="__('Your best sellers will be ranked here once sales are recorded.')"
                        />
                    @endforelse
                </x-ui.card>
            @endif

            {{-- Recent sales --}}
            @if ($recentOrders)
                <x-ui.card class="xl:col-span-2" :title="__('Recent Sales')" :description="__('Latest completed sales')" :padding="false">
                    @can('reports.view')
                        <x-slot:actions>
                            <x-ui.button :href="route('reports.sales', ['period' => 'this_month'])" variant="ghost" size="sm">
                                {{ __('View all') }} <x-icon name="arrow-right" class="size-3.5" />
                            </x-ui.button>
                        </x-slot:actions>
                    @endcan

                    @if ($recentOrders->isEmpty())
                        <x-ui.empty-state
                            icon="orders"
                            :title="__('No sales yet')"
                            :description="__('Recent sales will appear here as soon as they are recorded.')"
                        />
                    @else
                        <div class="overflow-x-auto">
                            <table class="min-w-full divide-y divide-gray-100 text-sm">
                                <thead class="bg-gray-50 text-left text-xs font-medium uppercase tracking-wide text-gray-500">
                                    <tr>
                                        <th scope="col" class="px-5 py-3">{{ __('Sale') }}</th>
                                        <th scope="col" class="px-5 py-3">{{ __('Customer') }}</th>
                                        <th scope="col" class="px-5 py-3">{{ __('Status') }}</th>
                                        <th scope="col" class="px-5 py-3 text-right">{{ __('Total') }}</th>
                                        <th scope="col" class="hidden px-5 py-3 text-right sm:table-cell">{{ __('Time') }}</th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-gray-100">
                                    @foreach ($recentOrders as $order)
                                        @php($status = strtolower((string) data_get($order, 'status')))
                                        <tr class="hover:bg-gray-50">
                                            <td class="whitespace-nowrap px-5 py-3 font-medium text-gray-900">
                                                @if (data_get($order, 'url'))
                                                    <a href="{{ data_get($order, 'url') }}" class="hover:text-primary-600">{{ data_get($order, 'number') }}</a>
                                                @else
                                                    #{{ data_get($order, 'number') }}
                                                @endif
                                            </td>
                                            <td class="px-5 py-3 text-gray-600">{{ data_get($order, 'customer') ?? __('Walk-in') }}</td>
                                            <td class="px-5 py-3"><x-ui.badge :color="$statusColors[$status] ?? 'warning'" dot>{{ ucfirst($status) }}</x-ui.badge></td>
                                            <td class="whitespace-nowrap px-5 py-3 text-right tabular-nums text-gray-900">{{ Money::format(data_get($order, 'total', 0)) }}</td>
                                            <td class="hidden whitespace-nowrap px-5 py-3 text-right text-gray-500 sm:table-cell">{{ optional(data_get($order, 'created_at'))->diffForHumans() }}</td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    @endif
                </x-ui.card>
            @endif

            {{-- Recent purchases --}}
            @if ($recentPurchases)
                <x-ui.card :title="__('Recent Purchases')" :padding="false">
                    @forelse ($recentPurchases as $purchase)
                        <a href="{{ $purchase['url'] }}" class="flex items-center gap-3 border-b border-gray-100 px-5 py-3 last:border-b-0 hover:bg-gray-50">
                            <div class="min-w-0 flex-1">
                                <p class="truncate text-sm font-medium text-gray-900">{{ $purchase['number'] }} · {{ $purchase['supplier'] }}</p>
                                <p class="text-xs text-gray-500">{{ Format::date($purchase['date']) }} · {{ $purchase['status']->label() }}</p>
                            </div>
                            <span class="text-sm font-medium tabular-nums text-gray-900">{{ Money::format($purchase['total']) }}</span>
                        </a>
                    @empty
                        <x-ui.empty-state icon="purchase-orders" :title="__('No purchases yet')" :description="__('Purchases you record will appear here.')" />
                    @endforelse
                </x-ui.card>
            @endif

            {{-- Daily report panel --}}
            @if ($dailyReport)
                <x-ui.card
                    class="xl:col-span-3"
                    :title="__('Daily Report')"
                    :description="$dailyReport['date']->isoFormat('dddd, D MMMM YYYY')"
                    data-daily-report
                >
                    <x-slot:actions>
                        <a href="{{ route('reports.daily', ['date' => $dailyReport['date']->toDateString()]) }}" class="inline-flex items-center gap-1.5 rounded-lg border border-gray-200 bg-white px-3 py-1.5 text-xs font-medium text-gray-600 transition hover:bg-gray-50 dark:border-gray-700 dark:bg-gray-800 dark:text-gray-400">
                            <x-icon name="document" class="size-3.5" />{{ __('Print / CSV') }}
                        </a>
                    </x-slot:actions>

                    <div class="grid grid-cols-2 gap-x-8 gap-y-2 sm:grid-cols-3 lg:grid-cols-6">
                        <div>
                            <p class="text-xs font-medium text-gray-500">{{ __('Orders') }}</p>
                            <p class="mt-0.5 text-lg font-semibold tabular-nums text-gray-900">{{ Number::format($dailyReport['sales_count']) }}</p>
                        </div>
                        <div>
                            <p class="text-xs font-medium text-gray-500">{{ __('Gross Sales') }}</p>
                            <p class="mt-0.5 text-lg font-semibold tabular-nums text-gray-900">{{ Money::format($dailyReport['sales_total']) }}</p>
                        </div>
                        <div>
                            <p class="text-xs font-medium text-gray-500">{{ __('Discounts') }}</p>
                            <p class="mt-0.5 text-lg font-semibold tabular-nums text-gray-900">{{ Money::format($dailyReport['discount']) }}</p>
                        </div>
                        <div>
                            <p class="text-xs font-medium text-gray-500">{{ __('Tax') }}</p>
                            <p class="mt-0.5 text-lg font-semibold tabular-nums text-gray-900">{{ Money::format($dailyReport['tax']) }}</p>
                        </div>
                        <div>
                            <p class="text-xs font-medium text-gray-500">{{ __('Returns') }}</p>
                            <p class="mt-0.5 text-lg font-semibold tabular-nums text-gray-900 text-danger-600">{{ Money::format($dailyReport['returns_total']) }}</p>
                        </div>
                        <div>
                            <p class="text-xs font-medium text-gray-500">{{ __('Net Revenue') }}</p>
                            <p class="mt-0.5 text-lg font-semibold tabular-nums text-gray-900">{{ Money::format($dailyReport['net_revenue']) }}</p>
                        </div>
                    </div>

                    @if (! empty($dailyReport['payments']))
                        <div class="mt-4 flex flex-wrap gap-3 border-t border-gray-100 pt-4">
                            @foreach ($dailyReport['payments'] as $method => $amount)
                                <span class="inline-flex items-center gap-1.5 rounded-full bg-gray-100 px-3 py-1 text-xs font-medium text-gray-700">
                                    {{ ucfirst($method) }}: {{ Money::format($amount) }}
                                </span>
                            @endforeach
                        </div>
                    @endif
                </x-ui.card>
            @endif
        </div>
    @endif
</x-app-layout>
