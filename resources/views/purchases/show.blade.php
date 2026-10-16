@use('App\Enums\PaymentStatus')
@use('App\Enums\PurchaseStatus')
@use('App\Support\Money')

@php
    /** @var \App\Models\Purchase $purchase */
    $canEdit = auth()->user()->can('purchases.edit');
    $formatQty = fn ($qty) => rtrim(rtrim(number_format((float) $qty, 3, '.', ','), '0'), '.');
@endphp

<x-app-layout>
    <x-slot name="title">{{ $purchase->purchase_number }}</x-slot>

    <x-ui.page-header :title="$purchase->purchase_number" :description="__('Purchase from :supplier on :date', ['supplier' => $purchase->supplier->name, 'date' => $purchase->purchase_date->isoFormat('D MMM YYYY')])">
        <x-slot:breadcrumbs>
            <a href="{{ route('purchases.index') }}" class="hover:text-gray-700">{{ __('Purchases') }}</a>
        </x-slot:breadcrumbs>
        <x-slot:actions>
            @if ($canEdit && $purchase->isDraft())
                <form method="POST" action="{{ route('purchases.status', $purchase) }}">
                    @csrf
                    @method('PATCH')
                    <input type="hidden" name="status" value="{{ PurchaseStatus::Completed->value }}">
                    <x-ui.button type="submit" variant="secondary" icon="check-circle">{{ __('Mark completed') }}</x-ui.button>
                </form>
                <x-ui.button :href="route('purchases.edit', $purchase)" icon="pencil">{{ __('Edit') }}</x-ui.button>
            @endif
        </x-slot:actions>
    </x-ui.page-header>

    <div class="grid grid-cols-1 gap-6 xl:grid-cols-3">
        <div class="space-y-6 xl:col-span-2">
            {{-- Items --}}
            <x-ui.card :title="__('Items')" :description="trans_choice(':count item|:count items', $purchase->items->count())" :padding="false">
                <x-ui.table>
                    <x-slot:head>
                        <th class="px-4 py-3">{{ __('Product') }}</th>
                        <th class="px-4 py-3 text-right">{{ __('Qty') }}</th>
                        <th class="hidden px-4 py-3 text-right sm:table-cell">{{ __('Unit cost') }}</th>
                        <th class="hidden px-4 py-3 text-right md:table-cell">{{ __('Discount') }}</th>
                        <th class="hidden px-4 py-3 text-right md:table-cell">{{ __('Tax') }}</th>
                        <th class="px-4 py-3 text-right">{{ __('Line total') }}</th>
                    </x-slot:head>
                    @foreach ($purchase->items as $item)
                        <tr>
                            <td class="px-4 py-3">
                                <p class="font-medium text-gray-900">
                                    {{ $item->product?->name }}
                                    @if ($item->product?->trashed()) <x-ui.badge size="sm">{{ __('Archived') }}</x-ui.badge> @endif
                                </p>
                                <p class="text-xs text-gray-500">
                                    @if ($item->variant) {{ $item->variant->name }} · {{ $item->variant->sku }} @else {{ $item->product?->sku }} @endif
                                </p>
                            </td>
                            <td class="whitespace-nowrap px-4 py-3 text-right tabular-nums text-gray-900">{{ $formatQty($item->quantity) }}</td>
                            <td class="hidden whitespace-nowrap px-4 py-3 text-right tabular-nums text-gray-600 sm:table-cell">{{ Money::format($item->purchase_price) }}</td>
                            <td class="hidden whitespace-nowrap px-4 py-3 text-right tabular-nums text-gray-600 md:table-cell">{{ (float) $item->discount > 0 ? '−'.Money::format($item->discount) : '—' }}</td>
                            <td class="hidden whitespace-nowrap px-4 py-3 text-right tabular-nums text-gray-600 md:table-cell">{{ (float) $item->tax > 0 ? '+'.Money::format($item->tax) : '—' }}</td>
                            <td class="whitespace-nowrap px-4 py-3 text-right font-medium tabular-nums text-gray-900">{{ Money::format($item->line_total) }}</td>
                        </tr>
                    @endforeach
                </x-ui.table>

                <x-slot:footer>
                    <dl class="ms-auto w-full space-y-1.5 text-sm sm:w-72">
                        <div class="flex justify-between"><dt class="text-gray-500">{{ __('Subtotal') }}</dt><dd class="tabular-nums text-gray-900">{{ Money::format($purchase->subtotal) }}</dd></div>
                        <div class="flex justify-between"><dt class="text-gray-500">{{ __('Discount') }}</dt><dd class="tabular-nums text-gray-900">−{{ Money::format($purchase->discount) }}</dd></div>
                        <div class="flex justify-between"><dt class="text-gray-500">{{ __('Tax') }}</dt><dd class="tabular-nums text-gray-900">+{{ Money::format($purchase->tax) }}</dd></div>
                        <div class="flex justify-between border-t border-gray-200 pt-2 text-base font-semibold"><dt class="text-gray-900">{{ __('Grand total') }}</dt><dd class="tabular-nums text-gray-900">{{ Money::format($purchase->total) }}</dd></div>
                    </dl>
                </x-slot:footer>
            </x-ui.card>

            @if ($purchase->notes)
                <x-ui.card :title="__('Notes')">
                    <p class="whitespace-pre-line text-sm leading-6 text-gray-600">{{ $purchase->notes }}</p>
                </x-ui.card>
            @endif
        </div>

        <div class="space-y-6">
            {{-- Purchase --}}
            <x-ui.card :title="__('Purchase')">
                <dl class="space-y-3 text-sm">
                    <div class="flex justify-between gap-4"><dt class="text-gray-500">{{ __('Number') }}</dt><dd class="font-medium text-gray-900">{{ $purchase->purchase_number }}</dd></div>
                    <div class="flex justify-between gap-4"><dt class="text-gray-500">{{ __('Date') }}</dt><dd class="text-gray-900">{{ $purchase->purchase_date->isoFormat('D MMM YYYY') }}</dd></div>
                    <div class="flex justify-between gap-4"><dt class="text-gray-500">{{ __('Status') }}</dt><dd><x-ui.badge :color="$purchase->status->color()" dot>{{ $purchase->status->label() }}</x-ui.badge></dd></div>
                    <div class="flex justify-between gap-4"><dt class="text-gray-500">{{ __('Payment') }}</dt><dd><x-ui.badge :color="$purchase->payment_status->color()">{{ $purchase->payment_status->label() }}</x-ui.badge></dd></div>
                    <div class="flex justify-between gap-4"><dt class="text-gray-500">{{ __('Created by') }}</dt><dd class="text-gray-900">{{ $purchase->creator?->name ?? '—' }}</dd></div>
                    <div class="flex justify-between gap-4"><dt class="text-gray-500">{{ __('Recorded') }}</dt><dd class="text-gray-900">{{ $purchase->created_at->isoFormat('D MMM YYYY, HH:mm') }}</dd></div>
                </dl>

                @if ($canEdit && ! $purchase->isCancelled())
                    <form method="POST" action="{{ route('purchases.payment-status', $purchase) }}" class="mt-5 flex items-end gap-2 border-t border-gray-100 pt-4">
                        @csrf
                        @method('PATCH')
                        <div class="flex-1">
                            <x-ui.select name="payment_status" :label="__('Update payment status')" :options="PaymentStatus::options()" :selected="$purchase->payment_status->value" />
                        </div>
                        <x-ui.button type="submit" variant="secondary">{{ __('Save') }}</x-ui.button>
                    </form>
                @endif
            </x-ui.card>

            {{-- Supplier --}}
            <x-ui.card :title="__('Supplier')">
                <dl class="space-y-3 text-sm">
                    <div class="flex justify-between gap-4">
                        <dt class="text-gray-500">{{ __('Name') }}</dt>
                        <dd class="text-right font-medium text-gray-900">
                            @can('suppliers.view')
                                <a href="{{ route('suppliers.show', $purchase->supplier) }}" class="hover:text-primary-600">{{ $purchase->supplier->name }}</a>
                            @else
                                {{ $purchase->supplier->name }}
                            @endcan
                        </dd>
                    </div>
                    <div class="flex justify-between gap-4"><dt class="text-gray-500">{{ __('Company') }}</dt><dd class="text-right text-gray-900">{{ $purchase->supplier->company_name ?? '—' }}</dd></div>
                    <div class="flex justify-between gap-4"><dt class="text-gray-500">{{ __('Phone') }}</dt><dd class="text-right text-gray-900">{{ $purchase->supplier->phone ?? '—' }}</dd></div>
                    <div class="flex justify-between gap-4"><dt class="text-gray-500">{{ __('Email') }}</dt><dd class="break-all text-right text-gray-900">{{ $purchase->supplier->email ?? '—' }}</dd></div>
                </dl>
            </x-ui.card>

            {{-- Danger zone --}}
            @if (! $purchase->isCancelled() && ($canEdit || ($purchase->isDraft() && auth()->user()->can('purchases.delete'))))
                <x-ui.card :title="__('Actions')">
                    <div class="space-y-2">
                        @if ($canEdit)
                            <form method="POST" action="{{ route('purchases.status', $purchase) }}" x-data x-on:submit="if (! confirm(@js(__('Cancel purchase :number? A cancelled purchase cannot be reopened.', ['number' => $purchase->purchase_number])))) $event.preventDefault()">
                                @csrf
                                @method('PATCH')
                                <input type="hidden" name="status" value="{{ PurchaseStatus::Cancelled->value }}">
                                <x-ui.button type="submit" variant="secondary" icon="x-circle" class="w-full">{{ __('Cancel purchase') }}</x-ui.button>
                            </form>
                        @endif
                        @if ($purchase->isDraft())
                            @can('purchases.delete')
                                <x-ui.button
                                    variant="ghost"
                                    icon="trash"
                                    class="w-full text-red-600 hover:bg-red-50"
                                    :confirm="[
                                        'action' => route('purchases.destroy', $purchase),
                                        'title' => __('Delete draft :number?', ['number' => $purchase->purchase_number]),
                                        'message' => __('The draft and its items will be removed. This cannot be undone.'),
                                    ]"
                                >{{ __('Delete draft') }}</x-ui.button>
                            @endcan
                        @endif
                    </div>
                    <p class="mt-3 text-xs text-gray-500">{{ __('Completed purchases cannot be edited or deleted; cancel them instead.') }}</p>
                </x-ui.card>
            @endif
        </div>
    </div>
</x-app-layout>
