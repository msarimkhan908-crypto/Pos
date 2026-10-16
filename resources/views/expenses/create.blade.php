<x-app-layout>
    <x-slot name="title">{{ __('New Expense') }}</x-slot>

    <x-ui.page-header :title="__('New Expense')">
        <x-slot:breadcrumbs>
            <a href="{{ route('expenses.index') }}" class="hover:text-gray-700">{{ __('Expenses') }}</a>
        </x-slot:breadcrumbs>
    </x-ui.page-header>

    @include('expenses._form')
</x-app-layout>
