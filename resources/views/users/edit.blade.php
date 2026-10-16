<x-app-layout>
    <x-slot name="title">{{ __('Edit :name', ['name' => $user->name]) }}</x-slot>

    <x-ui.page-header :title="__('Edit User')" :description="$user->name">
        <x-slot:actions>
            <x-ui.button :href="route('users.index')" variant="ghost" icon="arrow-left">{{ __('Back') }}</x-ui.button>
        </x-slot:actions>
    </x-ui.page-header>

    <form method="POST" action="{{ route('users.update', $user) }}" class="max-w-2xl">
        @csrf
        @method('PUT')
        @include('users._form', ['editing' => true])
        <div class="mt-4 flex gap-3">
            <x-ui.button type="submit">{{ __('Save Changes') }}</x-ui.button>
            <x-ui.button :href="route('users.index')" variant="ghost">{{ __('Cancel') }}</x-ui.button>
        </div>
    </form>
</x-app-layout>
