{{--
    Structured modal built on Breeze's <x-modal> (focus trap, escape, backdrop).
    Open:  x-on:click="$dispatch('open-modal', 'modal-name')"
    Close: x-on:click="$dispatch('close')" from inside, or $dispatch('close-modal', 'modal-name')
--}}
@props([
    'name',
    'title' => null,
    'description' => null,
    'maxWidth' => 'lg',
    'show' => false,
])

<x-modal :name="$name" :show="$show" :max-width="$maxWidth" focusable>
    <div {{ $attributes->class('flex flex-col') }}>
        @if ($title)
            <div class="flex items-start justify-between gap-4 border-b border-gray-100 px-6 py-4 dark:border-gray-700">
                <div>
                    <h2 class="text-base font-semibold text-gray-900 dark:text-gray-100">{{ $title }}</h2>
                    @if ($description)
                        <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">{{ $description }}</p>
                    @endif
                </div>
                <button
                    type="button"
                    x-on:click="$dispatch('close')"
                    class="-me-2 -mt-1 inline-flex size-8 items-center justify-center rounded-lg text-gray-400 transition hover:bg-gray-100 hover:text-gray-600 focus:outline-none focus-visible:ring-2 focus-visible:ring-primary-500 dark:hover:bg-gray-700 dark:hover:text-gray-300"
                >
                    <span class="sr-only">{{ __('Close') }}</span>
                    <x-icon name="x" class="size-4" />
                </button>
            </div>
        @endif

        <div class="px-6 py-5 text-sm text-gray-700 dark:text-gray-300">
            {{ $slot }}
        </div>

        @isset($footer)
            <div class="flex flex-col-reverse gap-2 border-t border-gray-100 bg-gray-50 px-6 py-4 sm:flex-row sm:justify-end dark:border-gray-700 dark:bg-gray-800/50">
                {{ $footer }}
            </div>
        @endisset
    </div>
</x-modal>
