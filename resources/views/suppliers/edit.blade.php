<x-app-layout>
    <x-slot name="title">{{ __('Edit :name', ['name' => $supplier->name]) }}</x-slot>

    <x-ui.page-header :title="__('Edit Supplier')" :description="$supplier->name">
        <x-slot:breadcrumbs>
            <a href="{{ route('suppliers.index') }}" class="hover:text-gray-700">{{ __('Suppliers') }}</a>
            <span class="mx-1">/</span>
            <a href="{{ route('suppliers.show', $supplier) }}" class="hover:text-gray-700">{{ $supplier->name }}</a>
        </x-slot:breadcrumbs>
    </x-ui.page-header>

    @include('suppliers._form')
</x-app-layout>
