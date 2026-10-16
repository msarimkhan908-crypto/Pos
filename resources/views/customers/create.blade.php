<x-app-layout>
    <x-slot name="title">{{ __('New Customer') }}</x-slot>

    <x-ui.page-header :title="__('New Customer')">
        <x-slot:breadcrumbs>
            <a href="{{ route('customers.index') }}" class="hover:text-gray-700">{{ __('Customers') }}</a>
        </x-slot:breadcrumbs>
    </x-ui.page-header>

    @include('customers._form')
</x-app-layout>
