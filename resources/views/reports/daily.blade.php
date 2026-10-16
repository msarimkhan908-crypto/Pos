@use('App\Support\Money')
@use('App\Enums\PaymentMethod')
@use('Illuminate\Support\Number')

<x-app-layout>
    <x-slot name="title">{{ __('Daily Report') }}</x-slot>

    <x-ui.page-header
        :title="__('Daily Report')"
        :description="$date->isoFormat('dddd, D MMMM YYYY')"
    >
        <x-slot:actions>
            <a href="{{ request()->fullUrlWithQuery(['export' => 'csv']) }}" class="inline-flex items-center gap-1.5 rounded-lg border border-gray-200 bg-white px-3 py-2 text-sm font-medium text-gray-700 shadow-sm transition hover:bg-gray-50 dark:border-gray-700 dark:bg-gray-800 dark:text-gray-300" data-export>
                <x-icon name="upload" class="size-4" />{{ __('CSV') }}
            </a>
            <button type="button" onclick="window.print()" class="inline-flex items-center gap-1.5 rounded-lg border border-gray-200 bg-white px-3 py-2 text-sm font-medium text-gray-700 shadow-sm transition hover:bg-gray-50 dark:border-gray-700 dark:bg-gray-800 dark:text-gray-300">
                <x-icon name="printer" class="size-4" />{{ __('Print') }}
            </button>
            <div class="inline-flex items-center divide-x divide-gray-200 overflow-hidden rounded-lg border border-gray-200 bg-white shadow-sm dark:divide-gray-700 dark:border-gray-700 dark:bg-gray-800">
                <a href="{{ route('reports.daily', ['date' => $date->subDay()->toDateString()]) }}" class="flex size-9 items-center justify-center text-gray-500 transition hover:bg-gray-50" title="{{ __('Previous day') }}">
                    <x-icon name="arrow-left" class="size-4" />
                </a>
                @if (! $date->isToday())
                    <a href="{{ route('reports.daily', ['date' => $date->addDay()->toDateString()]) }}" class="flex size-9 items-center justify-center text-gray-500 transition hover:bg-gray-50" title="{{ __('Next day') }}">
                        <x-icon name="arrow-right" class="size-4" />
                    </a>
                @else
                    <span class="flex size-9 cursor-not-allowed items-center justify-center text-gray-300" aria-disabled="true">
                        <x-icon name="arrow-right" class="size-4" />
                    </span>
                @endif
            </div>
        </x-slot:actions>
    </x-ui.page-header>

    <div class="space-y-6">
        {{-- Summary metrics --}}
        <x-ui.card :title="__('Sales Summary')" data-daily-report>
            <dl class="grid grid-cols-2 gap-x-8 gap-y-4 sm:grid-cols-3 lg:grid-cols-6">
                <div>
                    <dt class="text-xs font-medium text-gray-500">{{ __('Orders') }}</dt>
                    <dd class="mt-1 text-xl font-semibold tabular-nums text-gray-900">{{ Number::format($report['sales_count']) }}</dd>
                </div>
                <div>
                    <dt class="text-xs font-medium text-gray-500">{{ __('Avg. Order') }}</dt>
                    <dd class="mt-1 text-xl font-semibold tabular-nums text-gray-900">{{ Money::format($report['avg_order']) }}</dd>
                </div>
                <div>
                    <dt class="text-xs font-medium text-gray-500">{{ __('Gross Sales') }}</dt>
                    <dd class="mt-1 text-xl font-semibold tabular-nums text-gray-900">{{ Money::format($report['sales_total']) }}</dd>
                </div>
                <div>
                    <dt class="text-xs font-medium text-gray-500">{{ __('Discounts') }}</dt>
                    <dd class="mt-1 text-xl font-semibold tabular-nums text-gray-900">{{ Money::format($report['discount']) }}</dd>
                </div>
                <div>
                    <dt class="text-xs font-medium text-gray-500">{{ __('Tax') }}</dt>
                    <dd class="mt-1 text-xl font-semibold tabular-nums text-gray-900">{{ Money::format($report['tax']) }}</dd>
                </div>
                <div>
                    <dt class="text-xs font-medium text-gray-500">{{ __('Delivery') }}</dt>
                    <dd class="mt-1 text-xl font-semibold tabular-nums text-gray-900">{{ Money::format($report['delivery']) }}</dd>
                </div>
            </dl>

            <div class="mt-6 grid grid-cols-1 gap-4 border-t border-gray-100 pt-5 sm:grid-cols-3">
                <div class="rounded-lg bg-gray-50 p-4">
                    <dt class="text-xs font-medium text-gray-500">{{ __('Returns') }}</dt>
                    <dd class="mt-1 text-xl font-semibold tabular-nums text-danger-600">
                        {{ Money::format($report['returns_total']) }}
                        @if ($report['returns_count'])
                            <span class="text-sm font-normal text-gray-500">({{ Number::format($report['returns_count']) }})</span>
                        @endif
                    </dd>
                </div>
                <div class="rounded-lg bg-primary-50 p-4 sm:col-span-2">
                    <dt class="text-xs font-medium text-primary-700">{{ __('Net Revenue') }}</dt>
                    <dd class="mt-1 text-2xl font-bold tabular-nums text-primary-700">{{ Money::format($report['net_revenue']) }}</dd>
                </div>
            </div>
        </x-ui.card>

        {{-- Payment breakdown --}}
        @if (! empty($report['payments']))
            <x-ui.card :title="__('Payments by Method')">
                <dl class="divide-y divide-gray-100">
                    @foreach ($report['payments'] as $method => $amount)
                        <div class="flex items-center justify-between py-2 text-sm">
                            <dt class="font-medium text-gray-700">{{ PaymentMethod::tryFrom($method)?->label() ?? ucfirst($method) }}</dt>
                            <dd class="tabular-nums text-gray-900">{{ Money::format($amount) }}</dd>
                        </div>
                    @endforeach
                </dl>
            </x-ui.card>
        @endif
    </div>
</x-app-layout>
