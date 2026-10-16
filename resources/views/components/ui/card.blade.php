@props([
    'title' => null,
    'description' => null,
    'padding' => true,
])

<div {{ $attributes->class('overflow-hidden rounded-xl border border-gray-200 bg-white shadow-sm dark:border-gray-700 dark:bg-gray-800') }}>
    @if ($title || isset($actions))
        <div class="flex flex-wrap items-start justify-between gap-3 border-b border-gray-100 px-5 py-4 dark:border-gray-700">
            <div class="min-w-0">
                @if ($title)
                    <h3 class="text-base font-semibold text-gray-900 dark:text-gray-100">{{ $title }}</h3>
                @endif
                @if ($description)
                    <p class="mt-0.5 text-sm text-gray-500 dark:text-gray-400">{{ $description }}</p>
                @endif
            </div>

            @isset($actions)
                <div class="flex shrink-0 items-center gap-2">{{ $actions }}</div>
            @endisset
        </div>
    @endif

    <div @class(['p-5' => $padding])>
        {{ $slot }}
    </div>

    @isset($footer)
        <div class="border-t border-gray-100 bg-gray-50 px-5 py-3 dark:border-gray-700 dark:bg-gray-800/50">
            {{ $footer }}
        </div>
    @endisset
</div>
