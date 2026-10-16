@php
    $enabled = old('widgets', settings('dashboard.widgets'));
    $widgets = [
        'sales' => [__("Today's sales & orders"), __('Needs sales access')],
        'purchases' => [__("Today's purchases"), __('Needs purchases access')],
        'returns' => [__("Today's returns"), __('Needs returns access')],
        'expenses' => [__("Today's expenses"), __('Needs expenses access')],
        'customers' => [__('Total customers'), __('Needs customers access')],
        'sales_chart' => [__('Sales overview chart'), __('Needs sales access')],
        'recent_sales' => [__('Recent sales'), __('Needs sales access')],
        'recent_purchases' => [__('Recent purchases'), __('Needs purchases access')],
        'top_products' => [__('Top selling products'), __('Needs products access')],
    ];
@endphp

<x-ui.card :title="__('Dashboard widgets')" :description="__('Choose what the dashboard shows. Each user still only sees widgets their role allows. All figures come from recorded data.')">
    <input type="hidden" name="widgets" value="">
    <div class="grid gap-3 sm:grid-cols-2">
        @foreach ($widgets as $key => [$label, $hint])
            <label class="flex items-start gap-3 rounded-lg p-3 ring-1 ring-gray-200 hover:bg-gray-50 dark:ring-gray-700 dark:hover:bg-gray-700/50">
                <input type="checkbox" name="widgets[]" value="{{ $key }}" @checked(in_array($key, (array) $enabled, true))
                    class="mt-0.5 rounded border-gray-300 text-primary-600 focus:ring-primary-500 dark:border-gray-600" data-widget="{{ $key }}">
                <span>
                    <span class="block text-sm font-medium text-gray-900 dark:text-gray-100">{{ $label }}</span>
                    <span class="block text-xs text-gray-500 dark:text-gray-400">{{ $hint }}</span>
                </span>
            </label>
        @endforeach
    </div>
</x-ui.card>
