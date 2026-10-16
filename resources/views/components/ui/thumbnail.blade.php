@props([
    'src' => null,
    'alt' => '',
    'icon' => 'image',
    'size' => 'md',
    'initial' => null,
    'color' => null,
    'emoji' => null,
])

@php
    $sizes = [
        'sm' => 'size-9',
        'md' => 'size-11',
        'lg' => 'size-20',
        'xl' => 'size-40',
    ];
    $box = $sizes[$size] ?? $sizes['md'];
    $emojiFontSizes = ['sm' => 'text-xl', 'md' => 'text-2xl', 'lg' => 'text-4xl', 'xl' => 'text-6xl'];
    $iconSize = match ($size) {
        'sm' => 'size-4',
        'lg', 'xl' => 'size-8',
        default => 'size-5',
    };
@endphp

@if ($src)
    <img src="{{ $src }}" alt="{{ $alt }}" loading="lazy" {{ $attributes->class([$box, 'shrink-0 rounded-lg border border-gray-200 bg-white object-cover']) }}>
@elseif ($emoji)
    <span
        {{ $attributes->class([$box, $emojiFontSizes[$size] ?? 'text-2xl', 'flex shrink-0 items-center justify-center rounded-lg select-none']) }}
        style="background-color: {{ $color ?? '#e5e7eb' }}"
        aria-hidden="true"
    >{{ $emoji }}</span>
@else
    <span {{ $attributes->class([$box, 'flex shrink-0 items-center justify-center rounded-lg border border-gray-200 bg-gray-50 text-gray-300']) }} aria-hidden="true">
        <x-icon :name="$icon" class="{{ $iconSize }}" />
    </span>
@endif
