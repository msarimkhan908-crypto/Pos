{{--
    One print job for a placed order: the customer receipt, then each kitchen order ticket,
    each on its own page (a thermal printer cuts between them).
--}}
<x-print-layout :title="$sale->invoice_number" :back="auth()->user()->can('sales.create') ? ['url' => route('pos.index'), 'label' => __('Back to POS')] : null">
    @if ($withReceipt)
        <section class="print-sheet mx-auto w-fit bg-white shadow-sm ring-1 ring-gray-200" data-slip="customer">
            @include('prints.partials.receipt', ['sale' => $sale, ...\App\Support\Printing::receipt()])
        </section>
    @endif

    @foreach ($kitchenOrders as $order)
        <section class="print-sheet mx-auto w-fit bg-white shadow-sm ring-1 ring-gray-200" data-slip="kitchen">
            @include('prints.partials.kot', ['order' => $order, 'groups' => $order->itemsByStation(), ...\App\Support\Printing::kot()])
        </section>
    @endforeach
</x-print-layout>
