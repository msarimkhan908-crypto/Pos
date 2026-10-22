{{--
    Customer receipt. Used for printing, the POS "Sale complete" dialog and the live preview.
    $sale: App\Models\Sale (items.modifiers, payments, customer, user, table loaded)
    $config: settings()->receipt() (or preview values); $business: settings()->business()
    $headerImage: optional image URL (receipt header image, else the business logo)
    $sample: true when showing preview data
--}}
@use('App\Support\Money')
@use('App\Support\Format')
@use('App\Enums\PaymentMethod')

@php
    $c = $config;
    $align = fn (string $key) => ['left' => 'text-left', 'center' => 'text-center', 'right' => 'text-right'][$c[$key] ?? 'center'] ?? 'text-center';
    $logoHeight = match ($c['logo_size'] ?? 'md') {
        'sm' => '32px',
        'lg' => '72px',
        'custom' => ($c['logo_custom_height'] ?? '') !== '' ? ((int) $c['logo_custom_height']).'px' : '48px',
        default => '48px',
    };
    $num = fn ($amount) => Money::number($amount);
    $taxLabel = settings('tax.label') ?: __('Tax');
    $customerName = $sale->customer_name ?: $sale->customer?->name;
    $hasCustomerInfo = ($c['show_customer'] ?? true) && ($customerName || $sale->customer_phone || $sale->customer_address);
@endphp

<div @class([
        'paper',
        'paper-'.($c['width'] ?? '80mm'),
        'paper-text-'.($c['font_size'] ?? 'md'),
        'paper-bordered' => $c['show_borders'] ?? false,
        'paper-no-separators' => ! ($c['show_separators'] ?? true),
    ]) data-receipt data-width="{{ $c['width'] ?? '80mm' }}">

    @if ($sample ?? false)
        <p class="mb-2 rounded bg-amber-100 px-2 py-1 text-center font-sans text-[11px] font-bold uppercase tracking-wide text-amber-800" data-sample>{{ __('Sample data — preview only') }}</p>
    @endif

    {{-- Header --}}
    <div class="{{ $align('header_align') }}" data-receipt-header>
        @if (($c['show_logo'] ?? true) && ($headerImage ?? null))
            <img src="{{ $headerImage }}" alt="" style="max-height: {{ $logoHeight }}; max-width: 100%;"
                class="mb-1 inline-block object-contain" data-receipt-logo>
        @endif
        @if (filled($c['header_text'] ?? null))
            <p class="whitespace-pre-line">{{ $c['header_text'] }}</p>
        @endif
        @if (($c['show_address'] ?? true) && filled($business['address'] ?? null))
            <p class="whitespace-pre-line paper-muted">{{ $business['address'] }}</p>
        @endif
        @if (($c['show_phone'] ?? true) && filled($business['phone'] ?? null))
            <p class="paper-muted">{{ __('Contact') }}: {{ $business['phone'] }}</p>
        @endif
        @if (($c['show_email'] ?? false) && filled($business['email'] ?? null))
            <p class="paper-muted">{{ $business['email'] }}</p>
        @endif
        @if (($c['show_website'] ?? false) && filled($business['website'] ?? null))
            <p class="paper-muted">{{ $business['website'] }}</p>
        @endif
        @if (($c['show_tax_number'] ?? true) && filled($business['tax_number'] ?? null))
            <p class="paper-muted">{{ __('Tax No.') }}: {{ $business['tax_number'] }}</p>
        @endif
    </div>

    <div class="paper-sep"></div>

    {{-- Prominent order number --}}
    <p class="paper-huge text-center">{{ __('Order') }}: {{ $sale->order_no ?? $sale->id }}</p>

    <div class="paper-sep"></div>

    {{-- Sale details --}}
    <div>
        @if ($c['show_order_type'] ?? true)
            <div class="paper-row"><span>{{ __('Type') }}</span><span class="font-bold">{{ $sale->order_type?->label() }}@if ($sale->table) · {{ $sale->table->name }}@endif</span></div>
        @endif
        <div class="paper-row"><span>{{ __('Invoice #') }}</span><span class="font-bold">{{ $sale->invoice_number }}</span></div>
        @if ($c['show_cashier'] ?? true)
            <div class="paper-row"><span>{{ __('Cashier') }}</span><span>{{ $sale->user?->name ?? '—' }}</span></div>
        @endif
        <div class="paper-row"><span>{{ __('Date') }}</span><span>{{ Format::date($sale->completed_at) }} {{ Format::time($sale->completed_at) }}</span></div>
    </div>

    {{-- Customer / delivery details --}}
    @if ($c['show_customer'] ?? true)
        @if ($hasCustomerInfo)
            <div class="paper-sep"></div>
            <p class="text-center font-bold uppercase tracking-wide">{{ __('Delivery Details') }}</p>
            <div class="paper-sep"></div>
            <div>
                @if ($customerName)
                    <div class="paper-row"><span>{{ __('Customer') }}</span><span>{{ $customerName }}</span></div>
                @endif
                @if ($sale->customer_address)
                    <div class="paper-row"><span class="shrink-0">{{ __('Address') }}</span><span class="whitespace-pre-line">{{ $sale->customer_address }}</span></div>
                @endif
                @if ($sale->customer_phone)
                    <p class="mt-0.5"><span>{{ __('Phone') }}: {{ $sale->customer_phone }}</span></p>
                @endif
            </div>
        @else
            <div class="paper-row"><span>{{ __('Customer') }}</span><span>{{ __('Walk-in Customer') }}</span></div>
        @endif
    @endif

    <div class="paper-sep"></div>
    <p class="text-center font-bold uppercase tracking-wide">{{ __('Ordered Items') }}</p>
    <div class="paper-sep"></div>

    {{-- Items --}}
    {{-- For deal receipts: load components so we can print them indented under the deal parent. --}}
    @php
        $topLevelItems = $sale->items->filter(fn ($i) => $i->parent_sale_item_id === null);
        $componentsByParent = $sale->items->filter(fn ($i) => $i->parent_sale_item_id !== null)->groupBy('parent_sale_item_id');
    @endphp
    <table>
        <tbody>
            @foreach ($topLevelItems as $item)
                @php $isDeal = isset($componentsByParent[$item->id]) && $componentsByParent[$item->id]->count() > 0; @endphp
                <tr>
                    <td class="pr-2">
                        @if ($isDeal)
                            <span class="font-bold">{{ $item->displayName() }}</span>
                            <span class="text-[0.75em] font-bold uppercase">&nbsp;[{{ __('Deal') }}]</span>
                        @else
                            {{ $item->displayName() }}
                        @endif
                        @if (($c['item_layout'] ?? 'detailed') === 'detailed')
                            <span class="block text-[0.9em] paper-muted">{{ $num($item->unit_price) }} × {{ $item->quantity }}</span>
                        @endif
                        @foreach ($item->modifiers as $modifier)
                            <span class="block text-[0.9em] paper-muted">+ {{ $modifier->name }}@if ((float) $modifier->price > 0) ({{ $num($modifier->price) }})@endif</span>
                        @endforeach
                        @if (($c['show_item_notes'] ?? true) && $item->note)
                            <span class="block text-[0.9em] paper-muted">» {{ $item->note }}</span>
                        @endif
                        @if (($c['show_discount'] ?? true) && (float) $item->discount > 0)
                            <span class="block text-[0.9em] paper-muted">{{ __('Discount') }} −{{ $num($item->discount) }}</span>
                        @endif
                        {{-- Deal component lines indented under the deal name --}}
                        @foreach ($componentsByParent[$item->id] ?? [] as $component)
                            <span class="block text-[0.9em] paper-muted pl-2">· {{ $component->quantity }}× {{ $component->displayName() }}</span>
                        @endforeach
                    </td>
                    <td class="text-right tabular-nums">{{ $num($item->total) }}</td>
                </tr>
            @endforeach
        </tbody>
    </table>

    <div class="paper-sep"></div>

    {{-- Totals --}}
    <div class="tabular-nums">
        <div class="paper-row"><span>{{ __('Subtotal') }}</span><span>{{ $num($sale->subtotal) }}</span></div>
        @if ($c['show_discount'] ?? true)
            <div class="paper-row"><span>{{ __('Discount') }}</span><span>−{{ $num($sale->discount) }}</span></div>
        @endif
        @if (($c['show_tax'] ?? true) && (float) $sale->tax > 0)
            <div class="paper-row"><span>{{ $taxLabel }}</span><span>{{ $num($sale->tax) }}</span></div>
        @endif
        @if ((float) $sale->delivery_fee > 0)
            <div class="paper-row"><span>{{ __('Delivery Charges') }}</span><span>+{{ $num($sale->delivery_fee) }}</span></div>
        @endif
        <div class="paper-sep"></div>
        <div class="paper-row paper-big"><span>{{ __('Total Price') }}</span><span>{{ Money::format($sale->total) }}</span></div>
    </div>

    @if ($c['show_payment'] ?? true)
        <div class="paper-sep"></div>
        <div class="tabular-nums">
            <div class="paper-row"><span>{{ __('Payment') }}</span><span class="font-bold uppercase">{{ $sale->payment_method?->label() }}</span></div>
            @if ($sale->payment_method === PaymentMethod::Mixed)
                @foreach ($sale->payments as $payment)
                    <div class="paper-row"><span>{{ $payment->payment_method->label() }}</span><span>{{ $num($payment->amount) }}</span></div>
                @endforeach
            @elseif ($sale->payment_method === PaymentMethod::Cash)
                <div class="paper-row"><span>{{ __('Amount paid') }}</span><span>{{ $num($sale->cash_received) }}</span></div>
                <div class="paper-row font-bold"><span>{{ __('Change') }}</span><span>{{ $num($sale->change_amount) }}</span></div>
            @else
                <div class="paper-row"><span>{{ __('Amount paid') }}</span><span>{{ $num($sale->total) }}</span></div>
            @endif
        </div>
    @endif

    @if (filled($sale->notes))
        <div class="paper-sep"></div>
        <p class="whitespace-pre-line text-[0.9em]">{{ $sale->notes }}</p>
    @endif

    @if (filled($c['notes'] ?? null))
        <div class="paper-sep"></div>
        <p class="whitespace-pre-line text-[0.9em]">{{ $c['notes'] }}</p>
    @endif

    @if (($c['show_footer'] ?? true) && filled($c['footer_text'] ?? null))
        <div class="paper-sep"></div>
        <p class="whitespace-pre-line {{ $align('footer_align') }}" data-receipt-footer>{{ $c['footer_text'] }}</p>
    @endif
</div>
