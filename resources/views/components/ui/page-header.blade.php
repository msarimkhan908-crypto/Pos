@props([
    'title',
    'description' => null,
])

<div {{ $attributes->class('mb-6 flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between') }}>
    <div class="min-w-0">
        @isset($breadcrumbs)
            <nav aria-label="{{ __('Breadcrumb') }}" class="mb-1 text-sm text-gray-500 dark:text-gray-400">
                {{ $breadcrumbs }}
            </nav>
        @endisset

        <h1 class="truncate text-2xl font-semibold tracking-tight text-gray-900 dark:text-gray-100">{{ $title }}</h1>

        @if ($description)
            <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">{{ $description }}</p>
        @endif
    </div>

    @isset($actions)
        <div class="flex shrink-0 flex-wrap items-center gap-2">
            {{ $actions }}
        </div>
    @endisset
</div>
