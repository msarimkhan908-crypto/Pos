{{--
    Responsive table shell. Put <th> cells in the "head" slot and <tr> rows in the default slot.
--}}
<div {{ $attributes->class('overflow-x-auto') }}>
    <table class="min-w-full divide-y divide-gray-200 text-sm dark:divide-gray-700">
        @isset($head)
            <thead class="bg-gray-50 dark:bg-gray-700/50">
                <tr class="text-left text-xs font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400">
                    {{ $head }}
                </tr>
            </thead>
        @endisset
        <tbody class="divide-y divide-gray-100 bg-white dark:divide-gray-700 dark:bg-gray-800">
            {{ $slot }}
        </tbody>
    </table>
</div>
