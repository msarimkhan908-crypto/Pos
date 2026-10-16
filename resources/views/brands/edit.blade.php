<x-app-layout>
    <x-slot name="title">{{ __('Edit :name', ['name' => $brand->name]) }}</x-slot>

    <x-ui.page-header :title="__('Edit Brand')" :description="$brand->name">
        <x-slot:breadcrumbs>
            <a href="{{ route('brands.index') }}" class="hover:text-gray-700">{{ __('Brands') }}</a>
            <span class="mx-1">/</span>
            <a href="{{ route('brands.show', $brand) }}" class="hover:text-gray-700">{{ $brand->name }}</a>
        </x-slot:breadcrumbs>
    </x-ui.page-header>

    @include('brands._form')
</x-app-layout>
