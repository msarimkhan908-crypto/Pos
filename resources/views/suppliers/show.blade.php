@use('App\Support\Money')

<x-app-layout>
    <x-slot name="title">{{ $supplier->name }}</x-slot>

    <x-ui.page-header :title="$supplier->name" :description="$supplier->company_name">
        <x-slot:breadcrumbs>
            <a href="{{ route('suppliers.index') }}" class="hover:text-gray-700">{{ __('Suppliers') }}</a>
        </x-slot:breadcrumbs>
        <x-slot:actions>
            @can('purchases.create')
                @if ($supplier->isActive())
                    <x-ui.button :href="route('purchases.create', ['supplier' => $supplier->id])" variant="secondary" icon="plus">{{ __('New Purchase') }}</x-ui.button>
                @endif
            @endcan
            @can('suppliers.edit')
                <form method="POST" action="{{ route('suppliers.toggle', $supplier) }}">
                    @csrf
                    @method('PATCH')
                    <x-ui.button type="submit" variant="secondary" icon="power">{{ $supplier->isActive() ? __('Deactivate') : __('Activate') }}</x-ui.button>
                </form>
                <x-ui.button :href="route('suppliers.edit', $supplier)" icon="pencil">{{ __('Edit') }}</x-ui.button>
            @endcan
        </x-slot:actions>
    </x-ui.page-header>

    <div class="grid grid-cols-1 gap-6 lg:grid-cols-3">
        <div class="space-y-6">
            <x-ui.card :title="__('Supplier information')">
                <dl class="space-y-3 text-sm">
                    <div class="flex justify-between gap-4"><dt class="text-gray-500">{{ __('Status') }}</dt><dd><x-ui.status-badge :active="$supplier->isActive()" /></dd></div>
                    <div class="flex justify-between gap-4"><dt class="text-gray-500">{{ __('Supplier') }}</dt><dd class="text-right font-medium text-gray-900">{{ $supplier->name }}</dd></div>
                    <div class="flex justify-between gap-4"><dt class="text-gray-500">{{ __('Company') }}</dt><dd class="text-right text-gray-900">{{ $supplier->company_name ?? '—' }}</dd></div>
                    <div class="flex justify-between gap-4"><dt class="text-gray-500">{{ __('Since') }}</dt><dd class="text-gray-900">{{ $supplier->created_at->isoFormat('D MMM YYYY') }}</dd></div>
                </dl>
            </x-ui.card>

            <x-ui.card :title="__('Contact')">
                <dl class="space-y-3 text-sm">
                    <div class="flex justify-between gap-4">
                        <dt class="text-gray-500">{{ __('Phone') }}</dt>
                        <dd class="text-right text-gray-900">
                            @if ($supplier->phone) <a href="tel:{{ $supplier->phone }}" class="hover:text-primary-600">{{ $supplier->phone }}</a> @else — @endif
                        </dd>
                    </div>
                    <div class="flex justify-between gap-4">
                        <dt class="text-gray-500">{{ __('Email') }}</dt>
                        <dd class="break-all text-right text-gray-900">
                            @if ($supplier->email) <a href="mailto:{{ $supplier->email }}" class="hover:text-primary-600">{{ $supplier->email }}</a> @else — @endif
                        </dd>
                    </div>
                    <div>
                        <dt class="text-gray-500">{{ __('Address') }}</dt>
                        <dd class="mt-1 whitespace-pre-line text-gray-900">{{ $supplier->address ?? '—' }}</dd>
                    </div>
                </dl>
            </x-ui.card>

            <x-ui.card :title="__('Tax & registration')">
                <dl class="space-y-3 text-sm">
                    <div class="flex justify-between gap-4"><dt class="text-gray-500">{{ __('Tax number') }}</dt><dd class="text-gray-900">{{ $supplier->tax_number ?? '—' }}</dd></div>
                    <div class="flex justify-between gap-4"><dt class="text-gray-500">{{ __('Registration number') }}</dt><dd class="text-gray-900">{{ $supplier->registration_number ?? '—' }}</dd></div>
                </dl>
            </x-ui.card>

            @if ($supplier->notes)
                <x-ui.card :title="__('Notes')">
                    <p class="whitespace-pre-line text-sm leading-6 text-gray-600">{{ $supplier->notes }}</p>
                </x-ui.card>
            @endif
        </div>

        <x-ui.card
            class="lg:col-span-2"
            :title="__('Purchase history')"
            :description="$supplier->purchases_count > 0 ? __(':count purchases · :total total (excluding cancelled)', ['count' => $supplier->purchases_count, 'total' => Money::format($totalPurchased)]) : null"
            :padding="false"
        >
            @if ($purchases->isEmpty())
                <x-ui.empty-state icon="purchase-orders" :title="__('No purchases found for this supplier.')" :description="__('Purchases recorded for this supplier will be listed here.')" />
            @else
                <x-ui.table>
                    <x-slot:head>
                        <th class="px-4 py-3">{{ __('Purchase') }}</th>
                        <th class="px-4 py-3">{{ __('Date') }}</th>
                        <th class="px-4 py-3 text-right">{{ __('Total') }}</th>
                        <th class="hidden px-4 py-3 sm:table-cell">{{ __('Status') }}</th>
                        <th class="hidden px-4 py-3 md:table-cell">{{ __('Payment') }}</th>
                    </x-slot:head>
                    @foreach ($purchases as $purchase)
                        <tr class="hover:bg-gray-50">
                            <td class="px-4 py-3">
                                @can('purchases.view')
                                    <a href="{{ route('purchases.show', $purchase) }}" class="font-medium text-gray-900 hover:text-primary-600">{{ $purchase->purchase_number }}</a>
                                @else
                                    <span class="font-medium text-gray-900">{{ $purchase->purchase_number }}</span>
                                @endcan
                                <p class="text-xs text-gray-500">{{ $purchase->creator?->name }}</p>
                            </td>
                            <td class="whitespace-nowrap px-4 py-3 text-gray-600">{{ $purchase->purchase_date->isoFormat('D MMM YYYY') }}</td>
                            <td class="whitespace-nowrap px-4 py-3 text-right font-medium tabular-nums text-gray-900">{{ Money::format($purchase->total) }}</td>
                            <td class="hidden px-4 py-3 sm:table-cell"><x-ui.badge :color="$purchase->status->color()" dot>{{ $purchase->status->label() }}</x-ui.badge></td>
                            <td class="hidden px-4 py-3 md:table-cell"><x-ui.badge :color="$purchase->payment_status->color()">{{ $purchase->payment_status->label() }}</x-ui.badge></td>
                        </tr>
                    @endforeach
                </x-ui.table>
                @if ($purchases->hasPages())
                    <div class="border-t border-gray-100 px-4 py-3">{{ $purchases->links() }}</div>
                @endif
            @endif
        </x-ui.card>
    </div>

    @can('suppliers.delete')
        <div class="mt-6 flex justify-end">
            <x-ui.button
                variant="ghost"
                icon="trash"
                class="text-red-600 hover:bg-red-50"
                :confirm="[
                    'action' => route('suppliers.destroy', $supplier),
                    'title' => __('Delete :name?', ['name' => $supplier->name]),
                    'message' => __('Suppliers with purchases cannot be deleted. This cannot be undone.'),
                ]"
            >{{ __('Delete supplier') }}</x-ui.button>
        </div>
    @endcan
</x-app-layout>
