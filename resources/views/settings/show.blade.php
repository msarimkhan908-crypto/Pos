@php
    $titles = [
        'general' => __('General Settings'),
        'pos' => __('POS Settings'),
        'receipt' => __('Receipt Settings'),
        'tax' => __('Tax Settings'),
    ];
    $page = \App\Http\Controllers\SettingsController::SECTIONS[$section][0];
    $hasPreview = in_array($section, ['receipt', 'kot'], true);
@endphp

<x-app-layout>
    <x-slot name="title">{{ $titles[$page] }}</x-slot>

    <x-ui.page-header :title="$titles[$page]" :description="$readOnly ? __('You can view these settings. Only administrators can change them.') : null" />

    @if ($tabs->count() > 1)
        <nav class="-mx-1 mb-6 flex gap-1 overflow-x-auto border-b border-gray-200" aria-label="{{ __('Settings sections') }}">
            @foreach ($tabs as $key => $label)
                <a href="{{ route('settings.'.$key) }}" @if ($key === $section) aria-current="page" @endif
                    class="shrink-0 border-b-2 px-3 py-2 text-sm font-semibold {{ $key === $section ? 'border-primary-600 text-primary-700' : 'border-transparent text-gray-500 hover:text-gray-800' }}">{{ $label }}</a>
            @endforeach
        </nav>
    @endif

    <form method="POST" action="{{ route('settings.update', $section) }}" enctype="multipart/form-data" data-settings-form
        @if ($hasPreview)
            x-data="settingsPreview(@js(['url' => route('settings.preview', $section)]))"
            x-on:input.debounce.300ms="if ($event.target.type !== 'file' && !$event.target.closest('[data-no-preview]')) refresh()" x-on:change.debounce.150ms="if ($event.target.type !== 'file' && !$event.target.closest('[data-no-preview]')) refresh()"
        @endif
        class="{{ $hasPreview ? 'grid grid-cols-1 gap-6 xl:grid-cols-[minmax(0,1fr)_minmax(0,26rem)]' : 'max-w-4xl' }}">
        @csrf
        @method('PUT')

        <div class="space-y-6">
            <fieldset @disabled($readOnly) class="space-y-6">
                @include('settings.sections.'.$section)
            </fieldset>

            @unless ($readOnly)
                <div class="flex justify-end">
                    <x-ui.button type="submit">{{ __('Save settings') }}</x-ui.button>
                </div>
            @endunless
        </div>

        @if ($hasPreview)
            <aside class="xl:sticky xl:top-20 xl:self-start">
                <div class="rounded-xl bg-gray-200/70 p-4 ring-1 ring-gray-300">
                    <div class="mb-3 flex items-center justify-between">
                        <p class="text-sm font-semibold text-gray-800">{{ __('Live preview') }}</p>
                        <span class="rounded-full bg-amber-100 px-2 py-0.5 text-[11px] font-bold uppercase tracking-wide text-amber-800">{{ __('Sample data') }}</span>
                    </div>
                    <p x-show="error" x-cloak class="mb-2 rounded-md bg-red-50 px-3 py-2 text-xs text-red-700" x-text="error"></p>
                    <div class="max-h-[75vh] overflow-auto" x-bind:class="loading && 'opacity-60'">
                        <div class="mx-auto w-fit shadow-md" x-ref="preview" data-preview>
                            @if ($section === 'receipt')
                                @include('prints.partials.receipt', ['sale' => \App\Support\ReceiptSample::sale(), 'sample' => true, ...\App\Support\Printing::receipt()])
                            @else
                                @php($sampleKot = \App\Support\ReceiptSample::kitchenOrder())
                                @include('prints.partials.kot', ['order' => $sampleKot, 'groups' => $sampleKot->itemsByStation(), 'sample' => true, ...\App\Support\Printing::kot()])
                            @endif
                        </div>
                    </div>
                    <p class="mt-3 text-xs text-gray-600">{{ __('Updates as you change the settings. Nothing is saved until you press Save settings; no sale or order is created.') }}</p>
                </div>
            </aside>
        @endif
    </form>
</x-app-layout>
