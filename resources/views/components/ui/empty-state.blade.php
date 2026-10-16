@props([
    'icon' => 'info',
    'title',
    'description' => null,
])

<div {{ $attributes->class('flex flex-col items-center justify-center px-6 py-10 text-center') }}>
    <span class="flex size-12 items-center justify-center rounded-full bg-gray-100 text-gray-400 dark:bg-gray-700 dark:text-gray-500">
        <x-icon :name="$icon" class="size-6" />
    </span>
    <p class="mt-3 text-sm font-semibold text-gray-900 dark:text-gray-100">{{ $title }}</p>
    @if ($description)
        <p class="mt-1 max-w-xs text-sm text-gray-500 dark:text-gray-400">{{ $description }}</p>
    @endif
    @if ($slot->isNotEmpty())
        <div class="mt-4">{{ $slot }}</div>
    @endif
</div>
