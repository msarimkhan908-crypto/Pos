<x-app-layout>
    <x-slot name="title">{{ __('New Product') }}</x-slot>

    <x-ui.page-header :title="__('New Product')">
        <x-slot:breadcrumbs>
            <a href="{{ route('products.index') }}" class="hover:text-gray-700">{{ __('Products') }}</a>
        </x-slot:breadcrumbs>
    </x-ui.page-header>

    @include('products._form')
</x-app-layout>
