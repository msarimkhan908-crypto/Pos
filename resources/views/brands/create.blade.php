<x-app-layout>
    <x-slot name="title">{{ __('New Brand') }}</x-slot>

    <x-ui.page-header :title="__('New Brand')">
        <x-slot:breadcrumbs>
            <a href="{{ route('brands.index') }}" class="hover:text-gray-700">{{ __('Brands') }}</a>
        </x-slot:breadcrumbs>
    </x-ui.page-header>

    @include('brands._form')
</x-app-layout>
