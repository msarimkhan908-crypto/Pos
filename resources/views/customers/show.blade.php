<x-app-layout>
    <x-slot name="title">{{ $customer->name }}</x-slot>

    <x-ui.page-header :title="$customer->name" :description="$customer->phone">
        <x-slot:breadcrumbs>
            <a href="{{ route('customers.index') }}" class="hover:text-gray-700">{{ __('Customers') }}</a>
        </x-slot:breadcrumbs>
        <x-slot:actions>
            @can('customers.edit')
                <form method="POST" action="{{ route('customers.toggle', $customer) }}">
                    @csrf
                    @method('PATCH')
                    <x-ui.button type="submit" variant="secondary" icon="power">{{ $customer->isActive() ? __('Deactivate') : __('Activate') }}</x-ui.button>
                </form>
                <x-ui.button :href="route('customers.edit', $customer)" icon="pencil">{{ __('Edit') }}</x-ui.button>
            @endcan
        </x-slot:actions>
    </x-ui.page-header>

    <div class="grid grid-cols-1 gap-6 lg:grid-cols-3">
        <div class="space-y-6">
            <x-ui.card :title="__('Customer information')">
                <dl class="space-y-3 text-sm">
                    <div class="flex justify-between gap-4"><dt class="text-gray-500">{{ __('Status') }}</dt><dd><x-ui.status-badge :active="$customer->isActive()" /></dd></div>
                    <div class="flex justify-between gap-4"><dt class="text-gray-500">{{ __('Name') }}</dt><dd class="text-right font-medium text-gray-900">{{ $customer->name }}</dd></div>
                    <div class="flex justify-between gap-4">
                        <dt class="text-gray-500">{{ __('Phone') }}</dt>
                        <dd class="text-right text-gray-900">
                            @if ($customer->phone) <a href="tel:{{ $customer->phone }}" class="hover:text-primary-600">{{ $customer->phone }}</a> @else — @endif
                        </dd>
                    </div>
                    <div class="flex justify-between gap-4">
                        <dt class="text-gray-500">{{ __('Email') }}</dt>
                        <dd class="break-all text-right text-gray-900">
                            @if ($customer->email) <a href="mailto:{{ $customer->email }}" class="hover:text-primary-600">{{ $customer->email }}</a> @else — @endif
                        </dd>
                    </div>
                    <div>
                        <dt class="text-gray-500">{{ __('Address') }}</dt>
                        <dd class="mt-1 whitespace-pre-line text-gray-900">{{ $customer->address ?? '—' }}</dd>
                    </div>
                    <div class="flex justify-between gap-4"><dt class="text-gray-500">{{ __('Customer since') }}</dt><dd class="text-gray-900">{{ $customer->created_at->isoFormat('D MMM YYYY') }}</dd></div>
                </dl>
            </x-ui.card>

            <x-ui.card :title="__('Notes')">
                @if ($customer->notes)
                    <p class="whitespace-pre-line text-sm leading-6 text-gray-600">{{ $customer->notes }}</p>
                @else
                    <p class="text-sm text-gray-400">{{ __('No notes.') }}</p>
                @endif
            </x-ui.card>
        </div>

        <x-ui.card class="lg:col-span-2" :title="__('Purchase / Sales history')" :padding="false"
            :description="$sales->total() > 0 ? __(':count sales · :total total', ['count' => $sales->total(), 'total' => \App\Support\Money::format($totalSpent)]) : null">
            @if ($sales->isEmpty())
                <x-ui.empty-state icon="orders" :title="__('No sales found for this customer.')" :description="__('Completed POS sales to this customer will appear here.')" />
            @else
                <x-ui.table>
                    <x-slot:head>
                        <th class="px-4 py-3">{{ __('Invoice') }}</th>
                        <th class="px-4 py-3">{{ __('Date') }}</th>
                        <th class="hidden px-4 py-3 sm:table-cell">{{ __('Payment') }}</th>
                        <th class="px-4 py-3 text-right">{{ __('Total') }}</th>
                    </x-slot:head>
                    @foreach ($sales as $sale)
                        <tr class="hover:bg-gray-50">
                            <td class="px-4 py-3">
                                @can('sales.view')
                                    <a href="{{ route('sales.receipt', $sale) }}" class="font-medium text-gray-900 hover:text-primary-600">{{ $sale->invoice_number }}</a>
                                @else
                                    <span class="font-medium text-gray-900">{{ $sale->invoice_number }}</span>
                                @endcan
                            </td>
                            <td class="whitespace-nowrap px-4 py-3 text-gray-600">{{ $sale->completed_at->isoFormat('D MMM YYYY, HH:mm') }}</td>
                            <td class="hidden px-4 py-3 sm:table-cell">{{ $sale->payment_method?->label() }}</td>
                            <td class="whitespace-nowrap px-4 py-3 text-right font-medium tabular-nums text-gray-900">{{ \App\Support\Money::format($sale->total) }}</td>
                        </tr>
                    @endforeach
                </x-ui.table>
                @if ($sales->hasPages())
                    <div class="border-t border-gray-100 px-4 py-3">{{ $sales->links() }}</div>
                @endif
            @endif
        </x-ui.card>
    </div>

    @can('customers.delete')
        <div class="mt-6 flex justify-end">
            <x-ui.button
                variant="ghost"
                icon="trash"
                class="text-red-600 hover:bg-red-50"
                :confirm="[
                    'action' => route('customers.destroy', $customer),
                    'title' => __('Delete :name?', ['name' => $customer->name]),
                    'message' => __('This cannot be undone.'),
                ]"
            >{{ __('Delete customer') }}</x-ui.button>
        </div>
    @endcan
</x-app-layout>
