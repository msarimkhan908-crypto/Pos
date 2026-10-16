@props(['active'])

<x-ui.badge :color="$active ? 'success' : 'gray'" dot>
    {{ $active ? __('Active') : __('Inactive') }}
</x-ui.badge>
