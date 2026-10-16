<x-app-layout>
    <x-slot name="title">{{ __('Edit :name', ['name' => $customer->name]) }}</x-slot>

    <x-ui.page-header :title="__('Edit Customer')" :description="$customer->name">
        <x-slot:breadcrumbs>
            <a href="{{ route('customers.index') }}" class="hover:text-gray-700">{{ __('Customers') }}</a>
            <span class="mx-1">/</span>
            <a href="{{ route('customers.show', $customer) }}" class="hover:text-gray-700">{{ $customer->name }}</a>
        </x-slot:breadcrumbs>
    </x-ui.page-header>

    @include('customers._form')
</x-app-layout>
