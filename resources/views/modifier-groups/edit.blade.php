<x-app-layout>
    <x-slot name="title">{{ __('Edit :name', ['name' => $group->name]) }}</x-slot>

    <x-ui.page-header :title="__('Edit Modifier Group')" :description="$group->name">
        <x-slot:breadcrumbs>
            <a href="{{ route('modifier-groups.index') }}" class="hover:text-gray-700">{{ __('Modifiers') }}</a>
        </x-slot:breadcrumbs>
    </x-ui.page-header>

    @include('modifier-groups._form')
</x-app-layout>
