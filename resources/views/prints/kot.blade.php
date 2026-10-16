{{-- Printable kitchen order ticket (optionally one station's items). --}}
<x-print-layout :title="$order->kot_number" :back="['url' => route('kitchen.index'), 'label' => __('Kitchen screen')]">
    <section class="print-sheet mx-auto w-fit bg-white shadow-sm ring-1 ring-gray-200" data-slip="kitchen">
        @include('prints.partials.kot', ['order' => $order, 'groups' => $groups, ...\App\Support\Printing::kot()])
    </section>
</x-print-layout>
