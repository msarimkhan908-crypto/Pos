{{-- $station: App\Models\KitchenStation (new or existing) --}}
<form method="POST" action="{{ $station->exists ? route('kitchen.stations.update', $station) : route('kitchen.stations.store') }}" class="grid grid-cols-1 gap-6 lg:grid-cols-3">
    @csrf
    @if ($station->exists)
        @method('PUT')
    @endif

    <x-ui.card class="lg:col-span-2" :title="__('Details')">
        <div class="space-y-5">
            <x-ui.input name="name" :label="__('Name')" :value="$station->name" required maxlength="50" autofocus :hint="__('e.g. Main Kitchen, Grill, Pizza, Drinks, Dessert')" />
            <x-ui.input name="description" :label="__('Description')" :value="$station->description" maxlength="255" />
        </div>
    </x-ui.card>

    <x-ui.card :title="__('Settings')">
        <div class="space-y-5">
            <x-ui.checkbox name="is_active" :label="__('Active')" :description="__('Items of an inactive station are not sent to the kitchen.')" :checked="$station->is_active ?? true" />
            <x-ui.input name="sort_order" type="number" min="0" max="65535" :label="__('Display order')" :value="$station->sort_order ?? 0" />
        </div>
    </x-ui.card>

    <div class="flex flex-col-reverse gap-2 sm:flex-row sm:justify-end lg:col-span-3">
        <x-ui.button :href="route('kitchen.stations.index')" variant="secondary">{{ __('Cancel') }}</x-ui.button>
        <x-ui.button type="submit">{{ $station->exists ? __('Save changes') : __('Create station') }}</x-ui.button>
    </div>
</form>
