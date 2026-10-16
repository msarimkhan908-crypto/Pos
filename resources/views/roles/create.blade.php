<x-app-layout>
    <x-slot name="title">{{ __('New Role') }}</x-slot>

    <x-ui.page-header :title="__('New Role')" :description="__('Define a role and its permissions.')">
        <x-slot:actions>
            <x-ui.button :href="route('roles.index')" variant="ghost" icon="arrow-left">{{ __('Back') }}</x-ui.button>
        </x-slot:actions>
    </x-ui.page-header>

    <form method="POST" action="{{ route('roles.store') }}">
        @csrf
        @include('roles._form')
        <div class="mt-4 flex gap-3">
            <x-ui.button type="submit">{{ __('Create Role') }}</x-ui.button>
            <x-ui.button :href="route('roles.index')" variant="ghost">{{ __('Cancel') }}</x-ui.button>
        </div>
    </form>
</x-app-layout>
