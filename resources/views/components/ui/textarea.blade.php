@props([
    'name',
    'label' => null,
    'value' => null,
    'hint' => null,
    'id' => null,
    'rows' => 3,
])

@php
    $id ??= trim(str_replace(['[', ']'], ['_', ''], $name), '_');
    $errorKey = trim(str_replace(['[]', '[', ']'], ['', '.', ''], $name), '.');
    $error = isset($errors) ? $errors->first($errorKey) : null;

    if (request()->hasSession()) {
        $value = old($errorKey, $value);
    }

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

    <textarea
        id="{{ $id }}"
        name="{{ $name }}"
        rows="{{ $rows }}"
        @if ($error) aria-invalid="true" @endif
        @if ($describedBy) aria-describedby="{{ $describedBy }}" @endif
        {{ $attributes->class([
            'block w-full rounded-lg text-sm shadow-sm transition dark:bg-gray-700',
            'border-red-300 text-red-900 focus:border-red-500 focus:ring-red-500' => $error,
            'border-gray-300 text-gray-900 placeholder:text-gray-400 focus:border-primary-500 focus:ring-primary-500 dark:border-gray-600 dark:text-gray-100 dark:placeholder:text-gray-500' => ! $error,
        ]) }}
    >{{ $value }}</textarea>

    @if ($error)
        <p id="{{ $id }}-error" class="mt-1.5 text-sm text-red-600 dark:text-red-400">{{ $error }}</p>
    @elseif ($hint)
        <p id="{{ $id }}-hint" class="mt-1.5 text-sm text-gray-500 dark:text-gray-400">{{ $hint }}</p>
    @endif
</div>
