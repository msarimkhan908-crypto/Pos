{{-- $table: App\Models\RestaurantTable (new or existing) --}}
@use('App\Enums\TableStatus')

<form method="POST" action="{{ $table->exists ? route('restaurant.tables.update', $table) : route('restaurant.tables.store') }}" class="grid grid-cols-1 gap-6 lg:grid-cols-3">
    @csrf
    @if ($table->exists)
        @method('PUT')
    @endif

    <x-ui.card class="lg:col-span-2" :title="__('Details')">
        <div class="grid gap-5 sm:grid-cols-2">
            <x-ui.input name="name" :label="__('Table number / name')" :value="$table->name" required maxlength="50" autofocus :hint="__('e.g. Table 12, T12 or Patio 3')" />
            <x-ui.input name="section" :label="__('Section / location')" :value="$table->section" maxlength="50" :hint="__('Optional, e.g. Hall, Patio, Upstairs')" />
            <x-ui.input name="capacity" type="number" min="1" max="99" :label="__('Seats')" :value="$table->capacity" />
            <x-ui.input name="sort_order" type="number" min="0" max="65535" :label="__('Display order')" :value="$table->sort_order ?? 0" :hint="__('Lower numbers appear first.')" />
        </div>
    </x-ui.card>

    <x-ui.card :title="__('Status')">
        <div class="space-y-5">
            <x-ui.select name="status" :label="__('Status')" :options="collect(TableStatus::cases())->mapWithKeys(fn ($s) => [$s->value => $s->label()])->all()" :selected="$table->status?->value ?? 'available'" />
            <x-ui.checkbox name="is_active" :label="__('Active')" :description="__('Inactive tables are hidden from the POS.')" :checked="$table->is_active ?? true" />
        </div>
    </x-ui.card>

    <div class="flex flex-col-reverse gap-2 sm:flex-row sm:justify-end lg:col-span-3">
        <x-ui.button :href="route('restaurant.tables.index')" variant="secondary">{{ __('Cancel') }}</x-ui.button>
        <x-ui.button type="submit">{{ $table->exists ? __('Save changes') : __('Create table') }}</x-ui.button>
    </div>
</form>
