@props([
    'name',
    'label' => null,
    'options' => [],
    'selected' => null,
    'placeholder' => null,
    'hint' => null,
    'id' => null,
])

@php
    $id ??= trim(str_replace(['[', ']'], ['_', ''], $name), '_');
    $errorKey = trim(str_replace(['[]', '[', ']'], ['', '.', ''], $name), '.');
    $error = isset($errors) ? $errors->first($errorKey) : null;

    if (request()->hasSession()) {
        $selected = old($errorKey, $selected);
    }

    $selectedValues = array_map('strval', (array) $selected);
    $describedBy = $error ? "{$id}-error" : ($hint ? "{$id}-hint" : null);
@endphp

<div>
    @if ($label)
        <label for="{{ $id }}" class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-300">
            {{ $label }}
            @if ($attributes->has('required'))
                <span class="text-red-500" aria-hidden="true">*</span>
            @endif
        </label>
    @endif

    <select
        id="{{ $id }}"
        name="{{ $name }}"
        @if ($error) aria-invalid="true" @endif
        @if ($describedBy) aria-describedby="{{ $describedBy }}" @endif
        {{ $attributes->class([
            'block w-full rounded-lg py-2 ps-3 pe-10 text-sm shadow-sm transition disabled:cursor-not-allowed disabled:bg-gray-50 disabled:text-gray-500 dark:bg-gray-700 dark:disabled:bg-gray-800',
            'border-red-300 text-red-900 focus:border-red-500 focus:ring-red-500' => $error,
            'border-gray-300 text-gray-900 focus:border-primary-500 focus:ring-primary-500 dark:border-gray-600 dark:text-gray-100' => ! $error,
        ]) }}
    >
        @if ($placeholder)
            <option value="">{{ $placeholder }}</option>
        @endif

        @foreach ($options as $value => $text)
            <option value="{{ $value }}" @selected(in_array((string) $value, $selectedValues, true))>{{ $text }}</option>
        @endforeach

        {{ $slot }}
    </select>

    @if ($error)
        <p id="{{ $id }}-error" class="mt-1.5 text-sm text-red-600 dark:text-red-400">{{ $error }}</p>
    @elseif ($hint)
        <p id="{{ $id }}-hint" class="mt-1.5 text-sm text-gray-500 dark:text-gray-400">{{ $hint }}</p>
    @endif
</div>
