@use('App\Enums\RecordStatus')

<x-app-layout>
    <x-slot name="title">{{ $category->exists ? __('Edit :name', ['name' => $category->name]) : __('New Expense Category') }}</x-slot>

    <x-ui.page-header :title="$category->exists ? __('Edit :name', ['name' => $category->name]) : __('New Expense Category')">
        <x-slot:breadcrumbs>
            <a href="{{ route('expense-categories.index') }}" class="hover:text-gray-700">{{ __('Expense Categories') }}</a>
        </x-slot:breadcrumbs>
    </x-ui.page-header>

    <form method="POST" action="{{ $category->exists ? route('expense-categories.update', $category) : route('expense-categories.store') }}" class="max-w-2xl">
        @csrf
        @if ($category->exists)
            @method('PUT')
        @endif
        <x-ui.card>
            <div class="space-y-5">
                <x-ui.input name="name" :label="__('Name')" :value="$category->name" required maxlength="100" autofocus :hint="__('e.g. Rent, Electricity, Internet, Salaries')" />
                <x-ui.textarea name="description" :label="__('Description')" :value="$category->description" rows="2" maxlength="1000" />
                <x-ui.select name="status" :label="__('Status')" :options="RecordStatus::options()" :selected="$category->status?->value ?? 'active'" :hint="__('Inactive categories cannot be chosen for new expenses.')" />
            </div>
        </x-ui.card>
        <div class="mt-4 flex flex-col-reverse gap-2 sm:flex-row sm:justify-end">
            <x-ui.button :href="route('expense-categories.index')" variant="secondary">{{ __('Cancel') }}</x-ui.button>
            <x-ui.button type="submit">{{ $category->exists ? __('Save changes') : __('Create category') }}</x-ui.button>
        </div>
    </form>
</x-app-layout>
