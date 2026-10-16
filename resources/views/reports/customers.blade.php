@use('App\Support\Money')
@use('App\Support\Format')

<x-app-layout>
    <x-slot name="title">{{ __('Customer Report') }}</x-slot>

    <x-ui.page-header :title="__('Customer Report')" :description="__('Registered customers who bought in the period. Walk-in sales are not included.')" />

    @include('reports._toolbar', ['current' => 'reports.customers'])

    <x-ui.card :padding="false">
        @if ($customers->isEmpty())
            <x-ui.empty-state icon="customers" :title="__('No customer purchases in this period')" :description="__('Sales linked to a registered customer appear here.')" />
        @else
            <x-ui.table>
                <x-slot:head>
                    <th class="px-4 py-3">{{ __('Customer') }}</th>
                    <th class="px-4 py-3 text-right">{{ __('Purchases') }}</th>
                    <th class="px-4 py-3 text-right">{{ __('Total spent') }}</th>
                    <th class="hidden px-4 py-3 text-right md:table-cell">{{ __('Refunded') }}</th>
                    <th class="px-4 py-3">{{ __('Last purchase') }}</th>
                </x-slot:head>
                @foreach ($customers as $customer)
                    <tr class="hover:bg-gray-50">
                        <td class="px-4 py-3">
                            @can('customers.view')<a href="{{ route('customers.show', $customer->id) }}" class="font-medium text-gray-900 hover:text-primary-600">{{ $customer->name }}</a>@else<span class="font-medium text-gray-900">{{ $customer->name }}</span>@endcan
                            @if ($customer->phone)<span class="block text-xs text-gray-500">{{ $customer->phone }}</span>@endif
                        </td>
                        <td class="px-4 py-3 text-right tabular-nums">{{ (int) $customer->purchases }}</td>
                        <td class="whitespace-nowrap px-4 py-3 text-right font-medium tabular-nums">{{ Money::format($customer->spent) }}</td>
                        <td class="hidden whitespace-nowrap px-4 py-3 text-right tabular-nums md:table-cell">{{ Money::format($customer->refunded) }}</td>
                        <td class="whitespace-nowrap px-4 py-3 text-gray-600">{{ Format::dateTime(\Carbon\CarbonImmutable::parse($customer->last_purchase_at)) }}</td>
                    </tr>
                @endforeach
            </x-ui.table>
            @if ($customers->hasPages())
                <div class="no-print border-t border-gray-100 px-4 py-3">{{ $customers->links() }}</div>
            @endif
        @endif
    </x-ui.card>
</x-app-layout>
