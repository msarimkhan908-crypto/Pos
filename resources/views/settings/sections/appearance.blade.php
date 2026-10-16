@use('App\Settings\SettingDefinitions')
@use('App\Support\FontRegistry')

@php
    $a = settings()->group('appearance');
    $values = collect($a)->mapWithKeys(fn ($v, $k) => [substr($k, 11) => old(substr($k, 11), $v)])->all();
    $themes = ['indigo' => __('Indigo (default)'), 'blue' => __('Blue'), 'emerald' => __('Emerald'), 'rose' => __('Rose'), 'amber' => __('Amber'), 'dark' => __('Dark sidebar'), 'custom' => __('Custom')];
    $colors = [
        'primary_color' => [__('Primary colour'), __('Links, active menu items, highlights')],
        'button_color' => [__('Button colour'), __('Main action buttons')],
        'secondary_color' => [__('Secondary colour'), __('Checkout button and accents')],
        'sidebar_color' => [__('Sidebar colour'), __('Sidebar background')],
        'sidebar_text_color' => [__('Sidebar text colour'), __('Sidebar labels and icons')],
    ];
@endphp

<div x-data="appearanceSettings(@js(['themes' => SettingDefinitions::THEMES, 'values' => $values]))" class="space-y-6">
    {{-- Color scheme --}}
    <x-ui.card :title="__('Color Scheme')" :description="__('Choose how the interface appears. Auto follows your device setting.')">
        <div class="flex flex-wrap gap-3">
            @foreach (['light' => ['sun', __('Light')], 'dark' => ['moon', __('Dark')], 'auto' => ['monitor', __('Auto')]] as $scheme => [$icon, $label])
                <label class="relative flex cursor-pointer flex-col items-center gap-2 rounded-xl border-2 px-6 py-4 transition
                    {{ ($values['color_scheme'] ?? 'auto') === $scheme ? 'border-primary-500 bg-primary-50 text-primary-700' : 'border-gray-200 text-gray-600 hover:border-gray-300' }}">
                    <input type="radio" name="color_scheme" value="{{ $scheme }}" class="sr-only"
                        {{ ($values['color_scheme'] ?? 'auto') === $scheme ? 'checked' : '' }}
                        onchange="document.querySelectorAll('[name=color_scheme]').forEach(r => {
                            r.closest('label').classList.toggle('border-primary-500', r.checked);
                            r.closest('label').classList.toggle('bg-primary-50', r.checked);
                            r.closest('label').classList.toggle('text-primary-700', r.checked);
                            r.closest('label').classList.toggle('border-gray-200', !r.checked);
                            r.closest('label').classList.toggle('text-gray-600', !r.checked);
                        })">
                    <x-icon name="{{ $icon }}" class="size-6" />
                    <span class="text-sm font-medium">{{ $label }}</span>
                </label>
            @endforeach
        </div>
    </x-ui.card>

    <x-ui.card :title="__('Font family')" :description="__('Choose the typeface used across the application interface.')">
        <label class="block max-w-xs">
            <span class="text-sm font-medium text-gray-700 dark:text-gray-300">{{ __('Interface font') }}</span>
            <select name="font_family" class="mt-1 block w-full rounded-lg border-gray-300 text-sm focus:border-primary-500 focus:ring-primary-500 dark:bg-gray-700 dark:border-gray-600 dark:text-gray-100">
                @foreach (FontRegistry::options() as $key => $label)
                    <option value="{{ $key }}" {{ ($values['font_family'] ?? 'figtree') === $key ? 'selected' : '' }}>{{ $label }}</option>
                @endforeach
            </select>
        </label>
    </x-ui.card>

    <x-ui.card :title="__('Theme')" :description="__('Pick a preset, or choose your own colours below. Changes apply to every page after saving.')">
        <label class="block max-w-xs">
            <span class="text-sm font-medium text-gray-700 dark:text-gray-300">{{ __('Theme preference') }}</span>
            <select name="theme" x-model="theme" x-on:change="applyTheme()" class="mt-1 block w-full rounded-lg border-gray-300 text-sm focus:border-primary-500 focus:ring-primary-500 dark:bg-gray-700 dark:border-gray-600 dark:text-gray-100">
                @foreach ($themes as $value => $label)
                    <option value="{{ $value }}">{{ $label }}</option>
                @endforeach
            </select>
        </label>
    </x-ui.card>

    <x-ui.card :title="__('Colours')">
        <div class="grid gap-5 sm:grid-cols-2">
            @foreach ($colors as $key => [$label, $hint])
                <label class="block">
                    <span class="text-sm font-medium text-gray-700 dark:text-gray-300">{{ $label }}</span>
                    <span class="mt-1 flex items-center gap-2">
                        <input type="color" x-model="{{ $key }}" x-on:input="customised()" class="h-10 w-14 cursor-pointer rounded-md border border-gray-300 p-1 dark:border-gray-600" aria-label="{{ $label }}">
                        <input type="text" name="{{ $key }}" x-model="{{ $key }}" x-on:input="customised()" maxlength="7" pattern="#[0-9a-fA-F]{6}"
                            class="block w-32 rounded-lg border-gray-300 font-mono text-sm uppercase focus:border-primary-500 focus:ring-primary-500 dark:bg-gray-700 dark:border-gray-600 dark:text-gray-100">
                    </span>
                    <span class="mt-1 block text-xs text-gray-500">{{ $hint }}</span>
                    @error($key)<span class="mt-1 block text-sm text-red-600">{{ $message }}</span>@enderror
                </label>
            @endforeach
        </div>
    </x-ui.card>

    <x-ui.card :title="__('Preview')">
        <div class="flex overflow-hidden rounded-lg ring-1 ring-gray-200">
            <div class="w-40 space-y-1 p-3" x-bind:style="{ backgroundColor: sidebar_color, color: sidebar_text_color }">
                <p class="text-[11px] font-semibold uppercase opacity-60">{{ __('Menu') }}</p>
                <p class="rounded px-2 py-1 text-sm font-medium" x-bind:style="{ color: primary_color, backgroundColor: primary_color + '1a' }">{{ __('Dashboard') }}</p>
                <p class="px-2 py-1 text-sm">{{ __('POS') }}</p>
                <p class="px-2 py-1 text-sm">{{ __('Products') }}</p>
            </div>
            <div class="flex flex-1 flex-wrap items-center gap-3 bg-gray-50 p-4">
                <span class="rounded-lg px-4 py-2 text-sm font-semibold text-white" x-bind:style="{ backgroundColor: button_color }">{{ __('Save') }}</span>
                <span class="rounded-lg px-4 py-2 text-sm font-semibold text-white" x-bind:style="{ backgroundColor: secondary_color }">{{ __('Checkout') }}</span>
                <span class="text-sm font-semibold" x-bind:style="{ color: primary_color }">{{ __('A link') }}</span>
            </div>
        </div>
    </x-ui.card>
</div>
