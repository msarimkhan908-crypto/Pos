<x-app-layout>
    <x-slot name="title">{{ __('New Purchase') }}</x-slot>

    <x-ui.page-header :title="__('New Purchase')" :description="__('Record products bought from a supplier.')">
        <x-slot:breadcrumbs>
            <a href="{{ route('purchases.index') }}" class="hover:text-gray-700">{{ __('Purchases') }}</a>
        </x-slot:breadcrumbs>
    </x-ui.page-header>

    @include('purchases._form')
</x-app-layout>
