@props([
    'type' => 'info',
    'title' => null,
    'dismissible' => false,
])

@php
    $styles = [
        'info' => ['box' => 'border-sky-200 bg-sky-50 text-sky-800', 'icon' => 'info', 'iconColor' => 'text-sky-500'],
        'success' => ['box' => 'border-emerald-200 bg-emerald-50 text-emerald-800', 'icon' => 'check-circle', 'iconColor' => 'text-emerald-500'],
        'warning' => ['box' => 'border-amber-200 bg-amber-50 text-amber-800', 'icon' => 'alert-triangle', 'iconColor' => 'text-amber-500'],
        'danger' => ['box' => 'border-red-200 bg-red-50 text-red-800', 'icon' => 'x-circle', 'iconColor' => 'text-red-500'],
    ];

    $style = $styles[$type] ?? $styles['info'];
@endphp

<div
    role="{{ in_array($type, ['danger', 'warning']) ? 'alert' : 'status' }}"
    @if ($dismissible) x-data="{ open: true }" x-show="open" x-transition.opacity @endif
    {{ $attributes->class(['flex gap-3 rounded-lg border p-4 text-sm', $style['box']]) }}
>
    <x-icon :name="$style['icon']" class="mt-0.5 size-5 {{ $style['iconColor'] }}" />

    <div class="min-w-0 flex-1">
        @if ($title)
            <p class="font-semibold">{{ $title }}</p>
        @endif
        <div @class(['mt-1' => $title])>{{ $slot }}</div>
    </div>

    @if ($dismissible)
        <button
            type="button"
            x-on:click="open = false"
            class="-m-1 inline-flex size-7 shrink-0 items-center justify-center rounded-md opacity-70 transition hover:bg-black/5 hover:opacity-100 focus:outline-none focus-visible:ring-2 focus-visible:ring-current"
        >
            <span class="sr-only">{{ __('Dismiss') }}</span>
            <x-icon name="x" class="size-4" />
        </button>
    @endif
</div>
