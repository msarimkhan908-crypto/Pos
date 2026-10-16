<x-app-layout>
    <x-slot name="title">{{ __('New Modifier Group') }}</x-slot>

    <x-ui.page-header :title="__('New Modifier Group')">
        <x-slot:breadcrumbs>
            <a href="{{ route('modifier-groups.index') }}" class="hover:text-gray-700">{{ __('Modifiers') }}</a>
        </x-slot:breadcrumbs>
    </x-ui.page-header>

    @include('modifier-groups._form')
</x-app-layout>
