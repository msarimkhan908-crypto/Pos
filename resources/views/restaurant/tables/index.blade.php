@use('App\Enums\TableStatus')

<x-app-layout>
    <x-slot name="title">{{ __('Tables') }}</x-slot>

    <x-ui.page-header :title="__('Tables')" :description="__('Dining tables and their live status. Dine In orders occupy a table; it becomes available again when its kitchen orders are completed.')">
        <x-slot:actions>
            @can('tables.create')
                <x-ui.button :href="route('restaurant.tables.create')" icon="plus">{{ __('New Table') }}</x-ui.button>
            @endcan
        </x-slot:actions>
    </x-ui.page-header>

    <div class="mb-6 grid grid-cols-3 gap-3 sm:max-w-xl">
        @foreach (TableStatus::cases() as $status)
            <div class="rounded-xl bg-white px-4 py-3 shadow-sm ring-1 ring-gray-200">
                <p class="flex items-center gap-1.5 text-xs font-medium text-gray-500"><x-ui.badge :color="$status->color()" dot size="sm">{{ $status->label() }}</x-ui.badge></p>
                <p class="mt-1 text-2xl font-semibold tabular-nums text-gray-900" data-count="{{ $status->value }}">{{ $counts[$status->value] }}</p>
            </div>
        @endforeach
    </div>

    @if ($tables->isEmpty())
        <x-ui.card>
            <x-ui.empty-state icon="table" :title="__('No tables yet')" :description="__('Add your dining tables to take Dine In orders.')" />
        </x-ui.card>
    @else
        <div class="grid grid-cols-2 gap-4 sm:grid-cols-3 lg:grid-cols-4 xl:grid-cols-5">
            @foreach ($tables as $table)
                @php
                    $ring = match ($table->status) {
                        TableStatus::Available => 'ring-emerald-300',
                        TableStatus::Occupied => 'ring-red-300',
                        TableStatus::Reserved => 'ring-amber-300',
                    };
                @endphp
                <div @class(['flex flex-col rounded-xl bg-white p-4 shadow-sm ring-2', $ring, 'opacity-60' => ! $table->is_active]) data-table-card>
                    <div class="flex items-start justify-between gap-2">
                        <div class="min-w-0">
                            <p class="truncate text-lg font-bold text-gray-900">{{ $table->name }}</p>
                            <p class="text-xs text-gray-500">
                                {{ $table->section ?? '—' }}@if ($table->capacity) · {{ trans_choice(':count seat|:count seats', $table->capacity) }}@endif
                            </p>
                        </div>
                        <x-ui.badge :color="$table->status->color()" dot>{{ $table->status->label() }}</x-ui.badge>
                    </div>

                    @if (! $table->is_active)
                        <p class="mt-2 text-xs font-medium text-gray-500">{{ __('Inactive — hidden from the POS') }}</p>
                    @elseif ($table->open_kitchen_orders_count)
                        <p class="mt-2 text-xs font-medium text-amber-700">{{ trans_choice(':count order in the kitchen|:count orders in the kitchen', $table->open_kitchen_orders_count) }}</p>
                    @endif

                    @can('tables.status')
                        <div class="mt-3 grid grid-cols-3 gap-1">
                            @foreach (TableStatus::cases() as $status)
                                <form method="POST" action="{{ route('restaurant.tables.status', $table) }}">
                                    @csrf
                                    @method('PATCH')
                                    <input type="hidden" name="status" value="{{ $status->value }}">
                                    <button type="submit" @disabled($table->status === $status)
                                        class="w-full rounded-md px-1 py-1.5 text-[11px] font-semibold ring-1 ring-inset ring-gray-200 hover:bg-gray-50 disabled:bg-gray-900 disabled:text-white disabled:ring-gray-900">
                                        {{ $status->label() }}
                                    </button>
                                </form>
                            @endforeach
                        </div>
                    @endcan

                    @canany(['tables.edit', 'tables.delete'])
                        <div class="mt-3 flex justify-end gap-1 border-t border-gray-100 pt-2">
                            @can('tables.edit')
                                <x-ui.icon-button :href="route('restaurant.tables.edit', $table)" icon="pencil" :label="__('Edit')" />
                            @endcan
                            @can('tables.delete')
                                <x-ui.icon-button icon="trash" variant="danger" :label="__('Delete')" :confirm="[
                                    'action' => route('restaurant.tables.destroy', $table),
                                    'title' => __('Delete :name?', ['name' => $table->name]),
                                    'message' => __('Tables with orders cannot be deleted; deactivate them instead.'),
                                ]" />
                            @endcan
                        </div>
                    @endcanany
                </div>
            @endforeach
        </div>
    @endif
</x-app-layout>
