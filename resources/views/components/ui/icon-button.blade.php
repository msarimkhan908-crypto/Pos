{{--
    Compact icon-only action. $label is used for the tooltip and screen readers.
    Pass :confirm="['action' => url, 'title' => ..., 'message' => ...]" to open the shared delete dialog.
--}}
@props([
    'icon',
    'label',
    'href' => null,
    'type' => 'button',
    'variant' => 'default',
    'confirm' => null,
])

@php
    $classes = implode(' ', [
        'inline-flex size-8 items-center justify-center rounded-lg transition focus:outline-none focus-visible:ring-2 focus-visible:ring-primary-500',
        $variant === 'danger'
            ? 'text-gray-400 hover:bg-red-50 hover:text-red-600'
            : 'text-gray-400 hover:bg-gray-100 hover:text-gray-700',
    ]);
@endphp

@if ($href)
    <a href="{{ $href }}" title="{{ $label }}" {{ $attributes->class($classes) }}>
        <span class="sr-only">{{ $label }}</span>
        <x-icon :name="$icon" class="size-4" />
    </a>
@else
    <button
        type="{{ $type }}"
        title="{{ $label }}"
        @if ($confirm) x-on:click="$dispatch('confirm-delete', @js($confirm))" @endif
        {{ $attributes->class($classes) }}
    >
        <span class="sr-only">{{ $label }}</span>
        <x-icon :name="$icon" class="size-4" />
    </button>
@endif
