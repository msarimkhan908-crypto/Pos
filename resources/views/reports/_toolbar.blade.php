{{--
    Report navigation + period picker. $range: App\Support\DateRange; $current: route name;
    $selects: optional report filters, each [name, label, options, selected, placeholder].
--}}
@php
    $reports = [
        'reports.index' => __('Overview'),
        'reports.sales' => __('Sales'),
        'reports.purchases' => __('Purchases'),
        'reports.returns' => __('Returns'),
        'reports.expenses' => __('Expenses'),
        'reports.customers' => __('Customers'),
        'reports.products' => __('Product sales'),
    ];
    $periods = [
        'today' => __('Today'),
        'yesterday' => __('Yesterday'),
        'this_week' => __('This week'),
        'this_month' => __('This month'),
        'last_month' => __('Last month'),
        'custom' => __('Custom range'),
    ];
@endphp

<style>
    @media print {
        .app-sidebar, header, nav.report-tabs, form.report-filters, .no-print { display: none !important; }
        .app-main { padding: 0 !important; }
        body { background: #fff !important; }
    }
</style>

<nav class="report-tabs -mx-1 mb-4 flex gap-1 overflow-x-auto pb-1" aria-label="{{ __('Reports') }}">
    @foreach ($reports as $name => $label)
        <a href="{{ route($name, $range->query()) }}"
            @if ($current === $name) aria-current="page" @endif
            class="shrink-0 rounded-lg px-3 py-2 text-sm font-semibold {{ $current === $name ? 'bg-primary-600 text-white' : 'text-gray-600 hover:bg-gray-100' }}">{{ $label }}</a>
    @endforeach
</nav>

<form method="GET" class="report-filters mb-6 rounded-xl bg-white p-4 shadow-sm ring-1 ring-gray-200 dark:bg-gray-800 dark:ring-gray-700"
    x-data="{ period: @js($range->preset) }">
    <div class="grid gap-3 sm:grid-cols-2 lg:flex lg:flex-wrap lg:items-end">
        <label class="block lg:w-44">
            <span class="text-sm font-medium text-gray-700 dark:text-gray-300">{{ __('Period') }}</span>
            <select name="period" x-model="period" class="mt-1 block w-full rounded-lg border-gray-300 text-sm focus:border-primary-500 focus:ring-primary-500 dark:bg-gray-700 dark:border-gray-600 dark:text-gray-100">
                @foreach ($periods as $value => $label)
                    <option value="{{ $value }}" @selected($range->preset === $value)>{{ $label }}</option>
                @endforeach
            </select>
        </label>
        <template x-if="period === 'custom'">
            <div class="contents">
                <label class="block lg:w-40">
                    <span class="text-sm font-medium text-gray-700 dark:text-gray-300">{{ __('From') }}</span>
                    <input type="date" name="from" value="{{ $range->from->toDateString() }}" required class="mt-1 block w-full rounded-lg border-gray-300 text-sm focus:border-primary-500 focus:ring-primary-500 dark:bg-gray-700 dark:border-gray-600 dark:text-gray-100">
                </label>
                <label class="block lg:w-40">
                    <span class="text-sm font-medium text-gray-700 dark:text-gray-300">{{ __('To') }}</span>
                    <input type="date" name="to" value="{{ $range->to->toDateString() }}" required class="mt-1 block w-full rounded-lg border-gray-300 text-sm focus:border-primary-500 focus:ring-primary-500 dark:bg-gray-700 dark:border-gray-600 dark:text-gray-100">
                </label>
            </div>
        </template>

        @foreach ($selects ?? [] as $select)
            @include('reports._select', $select)
        @endforeach

        <div class="flex gap-2 lg:ml-auto">
            <x-ui.button type="submit" icon="filter">{{ __('Apply') }}</x-ui.button>
            @if ($current !== 'reports.index')
                <x-ui.button :href="request()->fullUrlWithQuery(['export' => 'csv'])" variant="secondary" icon="upload" data-export>{{ __('CSV') }}</x-ui.button>
            @endif
            <x-ui.button variant="secondary" icon="printer" onclick="window.print()">{{ __('Print') }}</x-ui.button>
        </div>
    </div>
    @if ($errors->any())
        <p class="mt-3 text-sm text-red-600" role="alert">{{ $errors->first() }}</p>
    @endif
</form>

<p class="mb-4 text-sm font-medium text-gray-600 dark:text-gray-400" data-period>{{ $range->label() }}</p>
