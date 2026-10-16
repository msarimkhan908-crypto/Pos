@props([
    'color' => 'gray',
    'size' => 'md',
    'dot' => false,
])

@php
    $colors = [
        'gray' => ['badge' => 'bg-gray-100 text-gray-700 ring-gray-500/10 dark:bg-gray-700 dark:text-gray-300 dark:ring-gray-500/30', 'dot' => 'bg-gray-400'],
        'primary' => ['badge' => 'bg-primary-50 text-primary-700 ring-primary-600/20 dark:bg-primary-900/40 dark:text-primary-300 dark:ring-primary-400/20', 'dot' => 'bg-primary-500'],
        'success' => ['badge' => 'bg-emerald-50 text-emerald-700 ring-emerald-600/20 dark:bg-emerald-900/40 dark:text-emerald-300 dark:ring-emerald-400/20', 'dot' => 'bg-emerald-500'],
        'warning' => ['badge' => 'bg-amber-50 text-amber-800 ring-amber-600/20 dark:bg-amber-900/40 dark:text-amber-300 dark:ring-amber-400/20', 'dot' => 'bg-amber-500'],
        'danger' => ['badge' => 'bg-red-50 text-red-700 ring-red-600/20 dark:bg-red-900/40 dark:text-red-300 dark:ring-red-400/20', 'dot' => 'bg-red-500'],
        'info' => ['badge' => 'bg-sky-50 text-sky-700 ring-sky-600/20 dark:bg-sky-900/40 dark:text-sky-300 dark:ring-sky-400/20', 'dot' => 'bg-sky-500'],
    ];

    $sizes = [
        'sm' => 'px-1.5 py-0.5 text-[11px]',
        'md' => 'px-2 py-0.5 text-xs',
        'lg' => 'px-2.5 py-1 text-sm',
    ];

    $color = $colors[$color] ?? $colors['gray'];
@endphp

<span {{ $attributes->class([
    'inline-flex items-center gap-1.5 whitespace-nowrap rounded-md font-medium ring-1 ring-inset',
    $color['badge'],
    $sizes[$size] ?? $sizes['md'],
]) }}>
    @if ($dot)
        <span class="size-1.5 rounded-full {{ $color['dot'] }}" aria-hidden="true"></span>
    @endif
    {{ $slot }}
</span>
