{{--
    Boolean checkbox. A hidden "0" input is always sent, so unchecking is saved.
--}}
@props([
    'name',
    'label',
    'description' => null,
    'checked' => false,
    'id' => null,
])

@php
    $id ??= $name;

    if (request()->hasSession() && session()->hasOldInput($name)) {
        $checked = (bool) old($name);
    }

    $error = isset($errors) ? $errors->first($name) : null;
@endphp

<div>
    <div class="flex items-start gap-3">
        <input type="hidden" name="{{ $name }}" value="0">
        <input
            id="{{ $id }}"
            type="checkbox"
            name="{{ $name }}"
            value="1"
            @checked($checked)
            {{ $attributes->class('mt-0.5 size-4 shrink-0 rounded border-gray-300 text-primary-600 focus:ring-primary-500') }}
        >
        <label for="{{ $id }}" class="text-sm">
            <span class="font-medium text-gray-900 dark:text-gray-100">{{ $label }}</span>
            @if ($description)
                <span class="mt-0.5 block text-gray-500 dark:text-gray-400">{{ $description }}</span>
            @endif
        </label>
    </div>

    @if ($error)
        <p class="mt-1.5 text-sm text-red-600 dark:text-red-400">{{ $error }}</p>
    @endif
</div>
