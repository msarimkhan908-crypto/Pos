<x-app-layout>
    <x-slot name="title">{{ __('Add Variant') }}</x-slot>

    <x-ui.page-header :title="__('Add Variant')" :description="$product->name">
        <x-slot:breadcrumbs>
            <a href="{{ route('products.index') }}" class="hover:text-gray-700">{{ __('Products') }}</a>
            <span class="mx-1">/</span>
            <a href="{{ route('products.show', $product) }}" class="hover:text-gray-700">{{ $product->name }}</a>
        </x-slot:breadcrumbs>
    </x-ui.page-header>

    @include('products.variants._form')
</x-app-layout>
