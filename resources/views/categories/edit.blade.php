<x-app-layout>
    <x-slot name="title">{{ __('Edit :name', ['name' => $category->name]) }}</x-slot>

    <x-ui.page-header :title="__('Edit Category')" :description="$category->name">
        <x-slot:breadcrumbs>
            <a href="{{ route('categories.index') }}" class="hover:text-gray-700">{{ __('Categories') }}</a>
            <span class="mx-1">/</span>
            <a href="{{ route('categories.show', $category) }}" class="hover:text-gray-700">{{ $category->name }}</a>
        </x-slot:breadcrumbs>
    </x-ui.page-header>

    @include('categories._form')
</x-app-layout>
