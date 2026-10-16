<x-app-layout>
    <x-slot name="title">{{ __('New Category') }}</x-slot>

    <x-ui.page-header :title="__('New Category')">
        <x-slot:breadcrumbs>
            <a href="{{ route('categories.index') }}" class="hover:text-gray-700">{{ __('Categories') }}</a>
        </x-slot:breadcrumbs>
    </x-ui.page-header>

    @include('categories._form')
</x-app-layout>
