@use('App\Enums\MoneyMethod')
@use('App\Support\Money')
@use('App\Support\Format')

<x-app-layout>
    <x-slot name="title">{{ $return->return_number }}</x-slot>

    <x-ui.page-header :title="$return->return_number" :description="__('Return for sale :invoice', ['invoice' => $return->sale->invoice_number])">
        <x-slot:breadcrumbs>
            <a href="{{ route('returns.index') }}" class="hover:text-gray-700">{{ __('Returns') }}</a>
        </x-slot:breadcrumbs>
        <x-slot:actions>
            @can('returns.delete')
                @unless ($return->status === \App\Enums\ReturnStatus::Completed)
                    <x-ui.button variant="danger" icon="trash" :confirm="[
                        'action' => route('returns.destroy', $return),
                        'title' => __('Delete :number?', ['number' => $return->return_number]),
                        'message' => __('Its items become returnable again. This cannot be undone.'),
                    ]">{{ __('Delete') }}</x-ui.button>
                @endunless
            @endcan
        </x-slot:actions>
    </x-ui.page-header>

    <div class="grid grid-cols-1 gap-6 lg:grid-cols-3">
        <x-ui.card :title="__('Details')">
            <dl class="space-y-3 text-sm">
                <div class="flex justify-between gap-4"><dt class="text-gray-500">{{ __('Status') }}</dt><dd><x-ui.badge :color="$return->status->color()" dot>{{ $return->status->label() }}</x-ui.badge></dd></div>
                <div class="flex justify-between gap-4"><dt class="text-gray-500">{{ __('Original sale') }}</dt><dd class="font-medium text-gray-900">
                    @can('sales.view')<a href="{{ route('sales.receipt', $return->sale) }}" class="hover:text-primary-600">{{ $return->sale->invoice_number }}</a>@else{{ $return->sale->invoice_number }}@endcan
                </dd></div>
                <div class="flex justify-between gap-4"><dt class="text-gray-500">{{ __('Sale date') }}</dt><dd class="text-gray-900">{{ Format::dateTime($return->sale->completed_at) }}</dd></div>
                <div class="flex justify-between gap-4"><dt class="text-gray-500">{{ __('Customer') }}</dt><dd class="text-right text-gray-900">{{ $return->customerLabel() }}</dd></div>
                <div class="flex justify-between gap-4"><dt class="text-gray-500">{{ __('Return date') }}</dt><dd class="text-gray-900">{{ Format::date($return->return_date) }}</dd></div>
                <div class="flex justify-between gap-4"><dt class="text-gray-500">{{ __('Reason') }}</dt><dd class="text-right text-gray-900">{{ $return->reason->label() }}</dd></div>
                <div class="flex justify-between gap-4"><dt class="text-gray-500">{{ __('Refund method') }}</dt><dd class="text-gray-900">{{ $return->refund_method->label() }}</dd></div>
                <div class="flex justify-between gap-4"><dt class="text-gray-500">{{ __('Processed by') }}</dt><dd class="text-gray-900">{{ $return->processor?->name ?? '—' }}</dd></div>
                <div class="flex justify-between gap-4 border-t border-gray-100 pt-3 text-base"><dt class="font-semibold text-gray-900">{{ __('Refund') }}</dt><dd class="font-bold tabular-nums text-gray-900" data-refund-amount>{{ Money::format($return->refund_amount) }}</dd></div>
            </dl>
            @if ($return->notes)
                <p class="mt-4 whitespace-pre-line rounded-lg bg-gray-50 p-3 text-sm text-gray-700">{{ $return->notes }}</p>
            @endif

            @if ($return->isPending())
                @can('returns.edit')
                    <div class="mt-5 space-y-2 border-t border-gray-100 pt-4">
                        <form method="POST" action="{{ route('returns.status', $return) }}" class="flex gap-2">
                            @csrf
                            @method('PATCH')
                            <input type="hidden" name="status" value="completed">
                            <select name="refund_method" class="flex-1 rounded-lg border-gray-300 text-sm focus:border-primary-500 focus:ring-primary-500 dark:bg-gray-700 dark:border-gray-600 dark:text-gray-100" aria-label="{{ __('Refund method') }}">
                                @foreach (MoneyMethod::options() as $value => $label)
                                    <option value="{{ $value }}" @selected($return->refund_method->value === $value)>{{ $label }}</option>
                                @endforeach
                            </select>
                            <x-ui.button type="submit" size="sm">{{ __('Complete & refund') }}</x-ui.button>
                        </form>
                        <form method="POST" action="{{ route('returns.status', $return) }}">
                            @csrf
                            @method('PATCH')
                            <input type="hidden" name="status" value="cancelled">
                            <x-ui.button type="submit" variant="secondary" size="sm" class="w-full">{{ __('Cancel return') }}</x-ui.button>
                        </form>
                    </div>
                @endcan
            @endif
        </x-ui.card>

        <x-ui.card class="lg:col-span-2" :title="__('Returned items')" :padding="false">
            <x-ui.table>
                <x-slot:head>
                    <th class="px-4 py-3">{{ __('Item') }}</th>
                    <th class="px-4 py-3 text-right">{{ __('Qty') }}</th>
                    <th class="px-4 py-3 text-right">{{ __('Unit price') }}</th>
                    <th class="hidden px-4 py-3 md:table-cell">{{ __('Reason') }}</th>
                    <th class="px-4 py-3 text-right">{{ __('Refund') }}</th>
                </x-slot:head>
                @foreach ($return->items as $item)
                    <tr>
                        <td class="px-4 py-3 font-medium text-gray-900">
                            {{ $item->saleItem->displayName() }}
                            @if ($item->saleItem->modifiers->isNotEmpty())
                                <span class="block text-xs font-normal text-gray-500">+ {{ $item->saleItem->modifiers->pluck('name')->join(', ') }}</span>
                            @endif
                        </td>
                        <td class="px-4 py-3 text-right tabular-nums">{{ $item->quantity }}</td>
                        <td class="px-4 py-3 text-right tabular-nums">{{ Money::format($item->unit_price) }}</td>
                        <td class="hidden px-4 py-3 text-gray-600 md:table-cell">{{ ($item->reason ?? $return->reason)->label() }}</td>
                        <td class="px-4 py-3 text-right font-medium tabular-nums">{{ Money::format($item->refund_amount) }}</td>
                    </tr>
                @endforeach
            </x-ui.table>
            <div class="flex justify-between border-t border-gray-100 px-4 py-3 text-sm">
                <span class="text-gray-500">{{ __('Value at sale price') }}: <span class="tabular-nums">{{ Money::format($return->subtotal) }}</span></span>
                <span class="font-semibold">{{ __('Refund') }}: <span class="tabular-nums">{{ Money::format($return->refund_amount) }}</span></span>
            </div>
        </x-ui.card>
    </div>
</x-app-layout>
