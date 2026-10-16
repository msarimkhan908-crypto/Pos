<x-app-layout>
    <x-slot name="title">{{ __('New Table') }}</x-slot>

    <x-ui.page-header :title="__('New Table')">
        <x-slot:breadcrumbs>
            <a href="{{ route('restaurant.tables.index') }}" class="hover:text-gray-700">{{ __('Tables') }}</a>
        </x-slot:breadcrumbs>
    </x-ui.page-header>

    @include('restaurant.tables._form')
</x-app-layout>
