@props(['item'])

@php
    $base = 'rail-center group flex items-center gap-3 rounded-lg px-3 py-2 text-sm font-medium transition-colors';
@endphp

@if ($item['available'])
    <a
        href="{{ $item['href'] }}"
        title="{{ $item['label'] }}"
        @if ($item['active']) aria-current="page" @endif
        @class([
            $base,
            'bg-primary-50 text-primary-700 dark:bg-primary-900/50 dark:text-primary-300' => $item['active'],
            'text-sidebar-text hover:bg-sidebar-text/10' => ! $item['active'],
        ])
    >
        <x-icon
            :name="$item['icon']"
            class="{{ $item['active'] ? 'text-primary-600 dark:text-primary-400' : 'text-sidebar-text/60 group-hover:text-sidebar-text' }}"
        />
        <span class="rail-hide truncate">{{ $item['label'] }}</span>
    </a>
@else
    {{-- Module not built yet: the route is not registered, so render a non-interactive placeholder. --}}
    <span
        title="{{ $item['label'] }} ({{ __('coming soon') }})"
        aria-disabled="true"
        data-nav-placeholder
        class="{{ $base }} cursor-not-allowed select-none text-sidebar-text/40"
    >
        <x-icon :name="$item['icon']" class="text-sidebar-text/30" />
        <span class="rail-hide flex-1 truncate">{{ $item['label'] }}</span>
        <span class="rail-hide rounded bg-gray-100 px-1.5 py-0.5 text-[10px] font-semibold uppercase tracking-wide text-gray-400 dark:bg-gray-700 dark:text-gray-500">
            {{ __('Soon') }}
        </span>
    </span>
@endif
