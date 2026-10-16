@props([
    'label',
    'value',
    'icon',
    'description' => null,
    'tone' => 'primary',
])

@php
    $tones = [
        'primary' => 'bg-primary-50 text-primary-600',
        'info' => 'bg-sky-50 text-sky-600',
        'success' => 'bg-emerald-50 text-emerald-600',
        'warning' => 'bg-amber-50 text-amber-600',
        'danger' => 'bg-red-50 text-red-600',
    ];
@endphp

<div {{ $attributes->class('rounded-xl border border-gray-200 bg-white p-5 shadow-sm') }}>
    <div class="flex items-start justify-between gap-4">
        <div class="min-w-0">
            <p class="truncate text-sm font-medium text-gray-500">{{ $label }}</p>
            <p class="mt-2 truncate text-2xl font-semibold tracking-tight text-gray-900 tabular-nums">{{ $value }}</p>
        </div>
        <span class="flex size-10 shrink-0 items-center justify-center rounded-lg {{ $tones[$tone] ?? $tones['primary'] }}">
            <x-icon :name="$icon" class="size-5" />
        </span>
    </div>

    @if ($description)
        <p class="mt-3 text-xs text-gray-500">{{ $description }}</p>
    @endif
</div>
