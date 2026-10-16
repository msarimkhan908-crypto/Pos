{{--
    Image picker with live preview. Photos over the upload limit are resized in the
    browser before submitting (see imageUpload in resources/js/app.js).
    Shows the current image and, when one exists, a "Remove image" checkbox named $removeName.
--}}
@use('App\Rules\ImageUpload')

@props([
    'name' => 'image',
    'label' => null,
    'current' => null,
    'removeName' => 'remove_image',
    'hint' => null,
])

@php
    $error = isset($errors) ? $errors->first($name) : null;
    $hint ??= ImageUpload::hint();
    $config = [
        'current' => $current,
        'maxBytes' => ImageUpload::maxKilobytes() * 1024,
        'resizeTo' => ImageUpload::RESIZE_TO_PX,
        'messages' => [
            'type' => __('Choose a JPG, PNG or WEBP image.'),
            'size' => __('This image is too large and could not be reduced below the upload limit. Try a smaller photo.'),
        ],
    ];
@endphp

<div x-data="imageUpload(@js($config))" {{ $attributes }}>
    @if ($label)
        <p class="mb-1.5 text-sm font-medium text-gray-700">{{ $label }}</p>
    @endif

    <div class="flex items-start gap-4">
        <div class="relative flex size-24 shrink-0 items-center justify-center overflow-hidden rounded-lg border border-gray-200 bg-gray-50 text-gray-300">
            @if ($current)
                <img src="{{ $current }}" alt="" class="size-full object-cover" x-show="preview && ! removed" x-bind:src="preview">
                <x-icon name="image" class="size-8" x-show="! preview || removed" x-cloak />
            @else
                <img alt="" class="size-full object-cover" x-show="preview && ! removed" x-bind:src="preview" x-cloak>
                <x-icon name="image" class="size-8" x-show="! preview || removed" />
            @endif
            <div class="absolute inset-0 flex items-center justify-center bg-white/80 text-xs font-medium text-gray-600" x-show="busy" x-cloak>
                {{ __('Resizing…') }}
            </div>
        </div>

        <div class="min-w-0 space-y-2">
            <label class="inline-flex cursor-pointer items-center gap-2 rounded-lg bg-white px-3 py-2 text-sm font-semibold text-gray-700 shadow-sm ring-1 ring-inset ring-gray-300 transition hover:bg-gray-50 focus-within:ring-2 focus-within:ring-primary-500">
                <x-icon name="upload" class="size-4" />
                <span>{{ $current ? __('Replace image') : __('Upload image') }}</span>
                <input
                    type="file"
                    name="{{ $name }}"
                    accept="image/*"
                    class="sr-only"
                    x-on:change.stop="pick($event)"
                    x-on:input.stop
                    @if ($error) aria-invalid="true" @endif
                >
            </label>

            @if ($current)
                <label class="flex items-center gap-2 text-sm text-gray-600">
                    <input type="checkbox" name="{{ $removeName }}" value="1" x-model="removed" class="size-4 rounded border-gray-300 text-red-600 focus:ring-red-500">
                    {{ __('Remove image') }}
                </label>
            @endif

            <p class="text-sm text-red-600" x-show="error" x-text="error" x-cloak></p>
            @if ($error)
                <p class="text-sm text-red-600" x-show="! error">{{ $error }}</p>
            @else
                <p class="text-xs text-gray-500" x-show="! error">{{ $hint }}</p>
            @endif
        </div>
    </div>
</div>
