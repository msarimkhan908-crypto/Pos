@props([
    'variant' => 'primary',
    'size' => 'md',
    'type' => 'button',
    'href' => null,
    'icon' => null,
    'confirm' => null,
])

@php
    $variants = [
        'primary' => 'bg-btn-600 text-white shadow-sm hover:bg-btn-500 focus-visible:outline-btn-600',
        'secondary' => 'bg-white text-gray-700 shadow-sm ring-1 ring-inset ring-gray-300 hover:bg-gray-50 focus-visible:outline-primary-600 dark:bg-gray-700 dark:text-gray-200 dark:ring-gray-600 dark:hover:bg-gray-600',
        'success' => 'bg-emerald-600 text-white shadow-sm hover:bg-emerald-500 focus-visible:outline-emerald-600',
        'danger' => 'bg-red-600 text-white shadow-sm hover:bg-red-500 focus-visible:outline-red-600',
        'ghost' => 'text-gray-700 hover:bg-gray-100 focus-visible:outline-primary-600 dark:text-gray-200 dark:hover:bg-gray-700',
    ];

    $sizes = [
        'sm' => 'gap-1.5 px-2.5 py-1.5 text-xs',
        'md' => 'gap-2 px-3.5 py-2 text-sm',
        'lg' => 'gap-2 px-5 py-2.5 text-base',
    ];

    $classes = implode(' ', [
        'inline-flex items-center justify-center rounded-lg font-semibold transition',
        'focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2',
        'disabled:pointer-events-none disabled:opacity-50',
        $variants[$variant] ?? $variants['primary'],
        $sizes[$size] ?? $sizes['md'],
    ]);

    $iconClass = $size === 'lg' ? 'size-5' : 'size-4';
@endphp

@if ($href)
    <a href="{{ $href }}" {{ $attributes->class($classes) }}>
        @if ($icon)
            <x-icon :name="$icon" class="{{ $iconClass }}" />
        @endif
        {{ $slot }}
    </a>
@else
    <button
        type="{{ $type }}"
        @if ($confirm) x-on:click="$dispatch('confirm-delete', @js($confirm))" @endif
        {{ $attributes->class($classes) }}
    >
        @if ($icon)
            <x-icon :name="$icon" class="{{ $iconClass }}" />
        @endif
        {{ $slot }}
    </button>
@endif
