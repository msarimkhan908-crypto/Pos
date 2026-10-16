<x-app-layout>
    <x-slot name="title">{{ __('New User') }}</x-slot>

    <x-ui.page-header :title="__('New User')" :description="__('Create a new system account.')">
        <x-slot:actions>
            <x-ui.button :href="route('users.index')" variant="ghost" icon="arrow-left">{{ __('Back') }}</x-ui.button>
        </x-slot:actions>
    </x-ui.page-header>

    <form method="POST" action="{{ route('users.store') }}" class="max-w-2xl">
        @csrf
        @include('users._form', ['editing' => false])
        <div class="mt-4 flex gap-3">
            <x-ui.button type="submit">{{ __('Create User') }}</x-ui.button>
            <x-ui.button :href="route('users.index')" variant="ghost">{{ __('Cancel') }}</x-ui.button>
        </div>
    </form>
</x-app-layout>
