@php
    /** @var \App\Models\ModifierGroup $group */
    $rows = old('modifiers', $group->exists
        ? $group->modifiers->map(fn ($m) => ['id' => $m->id, 'name' => $m->name, 'price' => $m->price, 'is_active' => $m->is_active])->all()
        : [['id' => null, 'name' => '', 'price' => '0', 'is_active' => true]]);

    $rows = collect($rows)->map(fn ($row) => [
        'id' => $row['id'] ?? null,
        'name' => $row['name'] ?? '',
        'price' => $row['price'] ?? '0',
        'is_active' => filter_var($row['is_active'] ?? false, FILTER_VALIDATE_BOOLEAN),
    ])->values();

    $rowErrors = collect($errors->getMessages())->filter(fn ($messages, $key) => str_starts_with($key, 'modifiers.'))->map(fn ($messages) => $messages[0]);
    $inputClass = 'block w-full rounded-lg border-gray-300 text-sm shadow-sm focus:border-primary-500 focus:ring-primary-500';
@endphp

<form
    method="POST"
    action="{{ $group->exists ? route('modifier-groups.update', $group) : route('modifier-groups.store') }}"
    class="grid grid-cols-1 gap-6 lg:grid-cols-3"
    x-data="{
        rows: @js($rows),
        errors: @js($rowErrors),
        add() { this.rows.push({ id: null, name: '', price: '0', is_active: true }); },
        remove(index) { this.rows.splice(index, 1); },
        error(index, field) { return this.errors[`modifiers.${index}.${field}`] ?? null; },
    }"
>
    @csrf
    @if ($group->exists)
        @method('PUT')
    @endif

    <x-ui.card class="lg:col-span-2" :title="__('Options')" :description="__('Each option adds its price to the item. Use 0 for free choices.')" :padding="false">
        <x-slot:actions>
            <x-ui.button variant="secondary" size="sm" icon="plus" x-on:click="add()">{{ __('Add option') }}</x-ui.button>
        </x-slot:actions>

        @error('modifiers')
            <x-ui.alert type="danger" class="m-5 mb-0">{{ $message }}</x-ui.alert>
        @enderror

        <div class="divide-y divide-gray-100">
            <template x-for="(row, index) in rows" :key="index">
                <div class="grid grid-cols-12 items-start gap-3 px-5 py-4">
                    <input type="hidden" x-bind:name="`modifiers[${index}][id]`" x-bind:value="row.id ?? ''">

                    <div class="col-span-12 sm:col-span-6">
                        <label x-bind:for="`modifier-name-${index}`" class="sr-only">{{ __('Option name') }}</label>
                        <input type="text" x-bind:id="`modifier-name-${index}`" x-bind:name="`modifiers[${index}][name]`" x-model="row.name" maxlength="100" placeholder="{{ __('e.g. Extra Cheese') }}" required class="{{ $inputClass }}" x-bind:class="error(index, 'name') && 'border-red-300'">
                        <p class="mt-1 text-sm text-red-600" x-show="error(index, 'name')" x-text="error(index, 'name')"></p>
                    </div>

                    <div class="col-span-6 sm:col-span-3">
                        <label x-bind:for="`modifier-price-${index}`" class="sr-only">{{ __('Price') }}</label>
                        <div class="relative">
                            <span class="pointer-events-none absolute inset-y-0 start-0 flex items-center ps-3 text-sm text-gray-400">+</span>
                            <input type="number" step="0.01" min="0" x-bind:id="`modifier-price-${index}`" x-bind:name="`modifiers[${index}][price]`" x-model="row.price" required class="{{ $inputClass }} ps-7 tabular-nums" x-bind:class="error(index, 'price') && 'border-red-300'">
                        </div>
                        <p class="mt-1 text-sm text-red-600" x-show="error(index, 'price')" x-text="error(index, 'price')"></p>
                    </div>

                    <div class="col-span-4 flex items-center pt-2 sm:col-span-2">
                        <input type="hidden" x-bind:name="`modifiers[${index}][is_active]`" value="0">
                        <label class="flex items-center gap-2 text-sm text-gray-600 dark:text-gray-400">
                            <input type="checkbox" value="1" x-bind:name="`modifiers[${index}][is_active]`" x-model="row.is_active" class="size-4 rounded border-gray-300 text-primary-600 focus:ring-primary-500 dark:border-gray-600">
                            {{ __('Active') }}
                        </label>
                    </div>

                    <div class="col-span-2 flex justify-end sm:col-span-1">
                        <button type="button" x-on:click="remove(index)" x-bind:disabled="rows.length === 1" class="inline-flex size-9 items-center justify-center rounded-lg text-gray-400 transition hover:bg-red-50 hover:text-red-600 disabled:cursor-not-allowed disabled:opacity-40" title="{{ __('Remove option') }}">
                            <span class="sr-only">{{ __('Remove option') }}</span>
                            <x-icon name="trash" class="size-4" />
                        </button>
                    </div>
                </div>
            </template>
        </div>
    </x-ui.card>

    <div class="space-y-6">
        <x-ui.card :title="__('Group')">
            <div class="space-y-5">
                <x-ui.input name="name" :label="__('Group name')" :value="$group->name" required maxlength="100" :placeholder="__('e.g. Burger Add-ons')" />
                <div class="grid grid-cols-2 gap-3">
                    <x-ui.input name="min_select" type="number" min="0" max="50" :label="__('Min. choices')" :value="$group->min_select ?? 0" required />
                    <x-ui.input name="max_select" type="number" min="1" max="50" :label="__('Max. choices')" :value="$group->max_select" :placeholder="__('No limit')" />
                </div>
                <p class="text-xs text-gray-500">{{ __('Min 0 makes the group optional. Min 1 and max 1 means “choose exactly one”.') }}</p>
                <x-ui.checkbox name="is_active" :label="__('Active')" :description="__('Inactive groups are not offered in the POS.')" :checked="$group->is_active" />
                <x-ui.input name="sort_order" type="number" min="0" max="65535" :label="__('Display order')" :value="$group->sort_order ?? 0" />
            </div>
        </x-ui.card>

        @if ($group->exists)
            <x-ui.alert type="info">
                {{ trans_choice('Used by :count product. Changes apply to it immediately.|Used by :count products. Changes apply to all of them immediately.', $group->products_count) }}
            </x-ui.alert>
        @endif
    </div>

    <div class="flex flex-col-reverse gap-2 sm:flex-row sm:justify-end lg:col-span-3">
        <x-ui.button :href="route('modifier-groups.index')" variant="secondary">{{ __('Cancel') }}</x-ui.button>
        <x-ui.button type="submit">{{ $group->exists ? __('Save changes') : __('Create group') }}</x-ui.button>
    </div>
</form>
