{{-- Printable customer receipt for one completed sale. --}}
<x-print-layout
    :title="$sale->invoice_number"
    :back="auth()->user()->can('sales.create') ? ['url' => route('pos.index'), 'label' => __('Back to POS')] : null"
    :edit="auth()->user()->can('sales.view') ? ['url' => route('sales.edit', $sale), 'label' => __('Edit Bill')] : null"
>
    <section class="print-sheet mx-auto w-fit bg-white shadow-sm ring-1 ring-gray-200" data-slip="customer">
        @include('prints.partials.receipt', ['sale' => $sale, ...\App\Support\Printing::receipt()])
    </section>
</x-print-layout>
