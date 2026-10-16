{{-- $category: App\Models\Category (new or existing) --}}
<form
    method="POST"
    action="{{ $category->exists ? route('categories.update', $category) : route('categories.store') }}"
    enctype="multipart/form-data"
    class="grid grid-cols-1 gap-6 lg:grid-cols-3"
>
    @csrf
    @if ($category->exists)
        @method('PUT')
    @endif

    <x-ui.card class="lg:col-span-2" :title="__('Details')">
        <div class="space-y-5">
            <x-ui.input name="name" :label="__('Name')" :value="$category->name" required maxlength="100" autofocus />
            <x-ui.input name="slug" :label="__('Slug')" :value="$category->slug" maxlength="120" :hint="__('Used in URLs. Leave blank to generate it from the name.')" />
            <x-ui.textarea name="description" :label="__('Description')" :value="$category->description" rows="4" maxlength="1000" />
        </div>
    </x-ui.card>

    <div class="space-y-6">
        <x-ui.card :title="__('Image')">
            <x-ui.image-upload name="image" :current="$category->image_url" />
        </x-ui.card>

        <x-ui.card :title="__('Settings')">
            <div class="space-y-5">
                <x-ui.checkbox name="is_active" :label="__('Active')" :description="__('Inactive categories are hidden from the POS.')" :checked="$category->is_active" />
                <x-ui.input name="sort_order" type="number" min="0" max="65535" :label="__('Display order')" :value="$category->sort_order ?? 0" :hint="__('Lower numbers appear first.')" />
                <x-ui.select name="kitchen_station_id" :label="__('Kitchen station')" :options="\App\Models\KitchenStation::options()" :selected="$category->kitchen_station_id" :placeholder="__('Not sent to the kitchen')" :hint="__('Where this category\'s items are prepared. Products can override it.')" />
            </div>
        </x-ui.card>
    </div>

    <div class="flex flex-col-reverse gap-2 sm:flex-row sm:justify-end lg:col-span-3">
        <x-ui.button :href="$category->exists ? route('categories.show', $category) : route('categories.index')" variant="secondary">{{ __('Cancel') }}</x-ui.button>
        <x-ui.button type="submit">{{ $category->exists ? __('Save changes') : __('Create category') }}</x-ui.button>
    </div>
</form>
