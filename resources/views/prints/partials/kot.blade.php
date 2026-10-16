{{--
    Kitchen order ticket (KOT). Used for printing and the live preview.
    $order: App\Models\KitchenOrder (items.saleItem.modifiers, items.station, table, sale, creator loaded)
    $groups: items grouped by station name (KitchenOrder::itemsByStation())
    $config: settings()->kot() (or preview values); $business: settings()->business()
    $sample: true when showing preview data
--}}
@use('App\Support\Format')

@php
    $c = $config;
    $align = ['left' => 'text-left', 'center' => 'text-center', 'right' => 'text-right'][$c['align'] ?? 'center'] ?? 'text-center';
    $showNotes = $c['show_notes'] ?? true;
    $itemSizeClass = match ($c['item_size'] ?? 'large') {
        'large' => 'kot-items-lg',
        'extra_large' => 'kot-items-xl',
        default => '',
    };
@endphp

<div @class([
        'paper',
        'paper-'.($c['width'] ?? '80mm'),
        'paper-text-'.($c['font_size'] ?? 'lg'),
        'paper-no-separators' => ! ($c['show_separators'] ?? true),
        $itemSizeClass => (bool) $itemSizeClass,
    ]) data-kot data-width="{{ $c['width'] ?? '80mm' }}">

    @if ($sample ?? false)
        <p class="mb-2 rounded bg-amber-100 px-2 py-1 text-center font-sans text-[11px] font-bold uppercase tracking-wide text-amber-800" data-sample>{{ __('Sample data — preview only') }}</p>
    @endif

    <div class="kot-header {{ $align }}">
        @if (($c['show_logo'] ?? false) && ($business['logo_url'] ?? null))
            <img src="{{ $business['logo_url'] }}" alt="" style="max-height: 40px; max-width: 100%;" class="mb-1 inline-block object-contain">
        @endif
        @if (filled($c['header_text'] ?? null))
            <p class="kot-small font-bold uppercase tracking-widest">{{ $c['header_text'] }}</p>
        @endif
        <p class="kot-order-no paper-huge">{{ $order->kot_number }}</p>
        @if ($order->order_no)
            <p class="kot-small font-semibold">{{ __('Order: :no', ['no' => $order->order_no]) }}</p>
        @endif
    </div>

    <div class="paper-sep"></div>

    <div>
        @if (($c['show_table'] ?? true) && $order->table)
            <div class="paper-row items-baseline"><span>{{ __('TABLE') }}</span><span class="paper-big">{{ $order->table->name }}</span></div>
        @endif
        @if ($c['show_order_type'] ?? true)
            <div class="paper-row"><span>{{ __('ORDER') }}</span><span class="font-bold uppercase">{{ $order->order_type->label() }}</span></div>
        @endif
        @if (($c['show_invoice'] ?? true) && $order->sale)
            <div class="paper-row kot-small"><span>{{ __('Order No.') }}</span><span>{{ $order->order_no ?? $order->sale->order_no }}</span></div>
            <div class="paper-row kot-small"><span>{{ __('Invoice') }}</span><span>{{ $order->sale->invoice_number }}</span></div>
        @endif
        <div class="paper-row kot-small"><span>{{ __('Date') }}</span><span>{{ Format::date($order->created_at) }} {{ Format::time($order->created_at) }}</span></div>
        @if (($c['show_cashier'] ?? true) && $order->creator)
            <div class="paper-row kot-small"><span>{{ __('Cashier') }}</span><span>{{ $order->creator->name }}</span></div>
        @endif
        @if (($c['show_customer'] ?? true) && $order->sale?->customer_name)
            <div class="paper-row kot-small"><span>{{ __('Customer') }}</span><span>{{ $order->sale->customer_name }}</span></div>
        @endif
    </div>

    @foreach ($groups as $station => $items)
        <div class="paper-sep"></div>
        @if ($c['show_station_headings'] ?? true)
            <p class="kot-small font-bold uppercase tracking-widest">{{ $station }}</p>
        @endif
        <ul class="mt-1 space-y-1">
            @foreach ($items as $item)
                <li class="kot-item">
                    <p class="font-bold leading-tight">{{ $item->quantity }} x {{ $item->saleItem->product_name }}@if ($item->saleItem->variant_name) ({{ $item->saleItem->variant_name }})@endif</p>
                    @foreach ($item->saleItem->modifiers as $modifier)
                        <p class="kot-addon">+ {{ $modifier->name }}</p>
                    @endforeach
                    @if ($showNotes && $item->notes)
                        <p class="kot-note">» {{ $item->notes }}</p>
                    @endif
                </li>
            @endforeach
        </ul>
    @endforeach

    @if ($showNotes && $order->notes)
        <div class="paper-sep"></div>
        <p class="kot-small font-bold uppercase tracking-widest">{{ __('Note') }}</p>
        <p class="kot-note font-bold whitespace-pre-line">{{ $order->notes }}</p>
    @endif

    @if (filled($c['footer_text'] ?? null))
        <div class="paper-sep"></div>
        <p class="whitespace-pre-line {{ $align }}">{{ $c['footer_text'] }}</p>
    @endif
</div>
