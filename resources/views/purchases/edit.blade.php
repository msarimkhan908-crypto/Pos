<x-app-layout>
    <x-slot name="title">{{ __('Edit :number', ['number' => $purchase->purchase_number]) }}</x-slot>

    <x-ui.page-header :title="__('Edit Purchase')" :description="$purchase->purchase_number.' · '.__('Draft')">
        <x-slot:breadcrumbs>
            <a href="{{ route('purchases.index') }}" class="hover:text-gray-700">{{ __('Purchases') }}</a>
            <span class="mx-1">/</span>
            <a href="{{ route('purchases.show', $purchase) }}" class="hover:text-gray-700">{{ $purchase->purchase_number }}</a>
        </x-slot:breadcrumbs>
    </x-ui.page-header>

    @include('purchases._form')
</x-app-layout>
