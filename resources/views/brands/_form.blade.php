{{-- $brand: App\Models\Brand (new or existing) --}}
<form
    method="POST"
    action="{{ $brand->exists ? route('brands.update', $brand) : route('brands.store') }}"
    enctype="multipart/form-data"
    class="grid grid-cols-1 gap-6 lg:grid-cols-3"
>
    @csrf
    @if ($brand->exists)
        @method('PUT')
    @endif

    <x-ui.card class="lg:col-span-2" :title="__('Details')">
        <div class="space-y-5">
            <x-ui.input name="name" :label="__('Name')" :value="$brand->name" required maxlength="100" autofocus />
            <x-ui.input name="slug" :label="__('Slug')" :value="$brand->slug" maxlength="120" :hint="__('Leave blank to generate it from the name.')" />
            <x-ui.textarea name="description" :label="__('Description')" :value="$brand->description" rows="4" maxlength="1000" />
        </div>
    </x-ui.card>

    <div class="space-y-6">
        <x-ui.card :title="__('Logo')">
            <x-ui.image-upload name="logo" remove-name="remove_logo" :current="$brand->logo_url" />
        </x-ui.card>

        <x-ui.card :title="__('Settings')">
            <x-ui.checkbox name="is_active" :label="__('Active')" :description="__('Inactive brands cannot be chosen for new products.')" :checked="$brand->is_active" />
        </x-ui.card>
    </div>

    <div class="flex flex-col-reverse gap-2 sm:flex-row sm:justify-end lg:col-span-3">
        <x-ui.button :href="$brand->exists ? route('brands.show', $brand) : route('brands.index')" variant="secondary">{{ __('Cancel') }}</x-ui.button>
        <x-ui.button type="submit">{{ $brand->exists ? __('Save changes') : __('Create brand') }}</x-ui.button>
    </div>
</form>
