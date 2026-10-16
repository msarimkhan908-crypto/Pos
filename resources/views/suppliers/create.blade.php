<x-app-layout>
    <x-slot name="title">{{ __('New Supplier') }}</x-slot>

    <x-ui.page-header :title="__('New Supplier')">
        <x-slot:breadcrumbs>
            <a href="{{ route('suppliers.index') }}" class="hover:text-gray-700">{{ __('Suppliers') }}</a>
        </x-slot:breadcrumbs>
    </x-ui.page-header>

    @include('suppliers._form')
</x-app-layout>
