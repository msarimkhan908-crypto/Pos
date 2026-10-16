<x-app-layout>
    <x-slot name="title">{{ __('Edit :name', ['name' => $role->name]) }}</x-slot>

    <x-ui.page-header :title="__('Edit Role')" :description="$role->name">
        <x-slot:actions>
            <x-ui.button :href="route('roles.show', $role)" variant="ghost" icon="arrow-left">{{ __('Back') }}</x-ui.button>
        </x-slot:actions>
    </x-ui.page-header>

    <form method="POST" action="{{ route('roles.update', $role) }}">
        @csrf
        @method('PUT')
        @include('roles._form')
        <div class="mt-4 flex gap-3">
            <x-ui.button type="submit">{{ __('Save Changes') }}</x-ui.button>
            <x-ui.button :href="route('roles.show', $role)" variant="ghost">{{ __('Cancel') }}</x-ui.button>
        </div>
    </form>
</x-app-layout>
