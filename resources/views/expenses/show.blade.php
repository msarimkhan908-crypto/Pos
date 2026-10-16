@use('App\Support\Money')
@use('App\Support\Format')

<x-app-layout>
    <x-slot name="title">{{ $expense->expense_number }}</x-slot>

    <x-ui.page-header :title="$expense->expense_number" :description="$expense->category->name">
        <x-slot:breadcrumbs>
            <a href="{{ route('expenses.index') }}" class="hover:text-gray-700">{{ __('Expenses') }}</a>
        </x-slot:breadcrumbs>
        <x-slot:actions>
            @can('expenses.edit')
                <x-ui.button :href="route('expenses.edit', $expense)" variant="secondary" icon="pencil">{{ __('Edit') }}</x-ui.button>
            @endcan
            @can('expenses.delete')
                <x-ui.button variant="danger" icon="trash" :confirm="[
                    'action' => route('expenses.destroy', $expense),
                    'title' => __('Delete :number?', ['number' => $expense->expense_number]),
                    'message' => __('This cannot be undone.'),
                ]">{{ __('Delete') }}</x-ui.button>
            @endcan
        </x-slot:actions>
    </x-ui.page-header>

    <x-ui.card class="max-w-2xl">
        <dl class="grid gap-4 text-sm sm:grid-cols-2">
            <div><dt class="text-gray-500">{{ __('Amount') }}</dt><dd class="mt-1 text-2xl font-bold tabular-nums text-gray-900">{{ Money::format($expense->amount) }}</dd></div>
            <div><dt class="text-gray-500">{{ __('Date') }}</dt><dd class="mt-1 text-gray-900">{{ Format::date($expense->expense_date) }}</dd></div>
            <div><dt class="text-gray-500">{{ __('Category') }}</dt><dd class="mt-1 text-gray-900">{{ $expense->category->name }}</dd></div>
            <div><dt class="text-gray-500">{{ __('Payment method') }}</dt><dd class="mt-1 text-gray-900">{{ $expense->payment_method->label() }}</dd></div>
            <div><dt class="text-gray-500">{{ __('Reference') }}</dt><dd class="mt-1 text-gray-900">{{ $expense->reference ?? '—' }}</dd></div>
            <div><dt class="text-gray-500">{{ __('Recorded by') }}</dt><dd class="mt-1 text-gray-900">{{ $expense->creator?->name ?? '—' }} · {{ Format::dateTime($expense->created_at) }}</dd></div>
            <div class="sm:col-span-2"><dt class="text-gray-500">{{ __('Description') }}</dt><dd class="mt-1 whitespace-pre-line text-gray-900">{{ $expense->description ?? '—' }}</dd></div>
        </dl>
    </x-ui.card>
</x-app-layout>
