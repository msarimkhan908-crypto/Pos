{{-- $expense: App\Models\Expense (new or existing); $categories: id => name --}}
@use('App\Enums\MoneyMethod')

<form method="POST" action="{{ $expense->exists ? route('expenses.update', $expense) : route('expenses.store') }}" class="grid grid-cols-1 gap-6 lg:grid-cols-3">
    @csrf
    @if ($expense->exists)
        @method('PUT')
    @endif

    <x-ui.card class="lg:col-span-2" :title="__('Expense')">
        @if ($categories->isEmpty())
            <p class="mb-4 rounded-md bg-amber-50 px-3 py-2 text-sm text-amber-800">
                {{ __('Add an expense category first.') }}
                @can('expenses.edit')
                    <a href="{{ route('expense-categories.create') }}" class="font-semibold underline">{{ __('New category') }}</a>
                @endcan
            </p>
        @endif
        <div class="grid gap-5 sm:grid-cols-2">
            <x-ui.select name="category_id" :label="__('Category')" :options="$categories->all()" :selected="$expense->category_id" :placeholder="__('Choose a category')" required />
            <x-ui.input name="amount" type="number" step="0.01" min="0.01" :label="__('Amount')" :value="$expense->amount" required />
            <x-ui.input name="expense_date" type="date" :label="__('Date')" :value="$expense->expense_date?->toDateString()" :max="today()->toDateString()" required />
            <x-ui.select name="payment_method" :label="__('Payment method')" :options="MoneyMethod::options()" :selected="$expense->payment_method?->value" required />
            <x-ui.input name="reference" :label="__('Reference')" :value="$expense->reference" maxlength="100" :hint="__('Bill or receipt number (optional)')" />
        </div>
        <div class="mt-5">
            <x-ui.textarea name="description" :label="__('Description')" :value="$expense->description" rows="3" maxlength="1000" />
        </div>
    </x-ui.card>

    <div class="flex flex-col-reverse gap-2 sm:flex-row sm:justify-end lg:col-span-3">
        <x-ui.button :href="$expense->exists ? route('expenses.show', $expense) : route('expenses.index')" variant="secondary">{{ __('Cancel') }}</x-ui.button>
        <x-ui.button type="submit">{{ $expense->exists ? __('Save changes') : __('Record expense') }}</x-ui.button>
    </div>
</form>
