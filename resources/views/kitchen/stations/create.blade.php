<x-app-layout>
    <x-slot name="title">{{ __('New Station') }}</x-slot>

    <x-ui.page-header :title="__('New Station')">
        <x-slot:breadcrumbs>
            <a href="{{ route('kitchen.stations.index') }}" class="hover:text-gray-700">{{ __('Kitchen Stations') }}</a>
        </x-slot:breadcrumbs>
    </x-ui.page-header>

    @include('kitchen.stations._form')
</x-app-layout>
