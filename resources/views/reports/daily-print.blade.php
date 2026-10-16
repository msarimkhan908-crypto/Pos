@use('App\Support\Money')
@use('App\Enums\PaymentMethod')
@use('Illuminate\Support\Number')
<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ __('Daily Report') }} · {{ $date->toDateString() }}</title>
    <style>
        body { font-family: sans-serif; font-size: 13px; color: #111; margin: 0; padding: 24px; }
        h1 { font-size: 18px; margin: 0 0 4px; }
        p.sub { color: #555; font-size: 12px; margin: 0 0 20px; }
        table { width: 100%; border-collapse: collapse; }
        td, th { padding: 6px 10px; text-align: left; }
        th { background: #f3f4f6; font-size: 11px; text-transform: uppercase; letter-spacing: .05em; }
        td.num { text-align: right; font-variant-numeric: tabular-nums; }
        tr.total td { font-weight: 700; border-top: 2px solid #e5e7eb; }
        @media print { button { display: none; } }
    </style>
</head>
<body>
    <button onclick="window.print()" style="margin-bottom:12px;padding:6px 14px;cursor:pointer;">{{ __('Print') }}</button>
    <h1>{{ __('Daily Report') }}</h1>
    <p class="sub">{{ $date->isoFormat('dddd, D MMMM YYYY') }}</p>

    <table>
        <thead>
            <tr>
                <th>{{ __('Metric') }}</th>
                <th style="text-align:right">{{ __('Value') }}</th>
            </tr>
        </thead>
        <tbody>
            <tr><td>{{ __('Orders') }}</td><td class="num">{{ Number::format($report['sales_count']) }}</td></tr>
            <tr><td>{{ __('Avg. Order') }}</td><td class="num">{{ Money::format($report['avg_order']) }}</td></tr>
            <tr><td>{{ __('Gross Sales') }}</td><td class="num">{{ Money::format($report['sales_total']) }}</td></tr>
            <tr><td>{{ __('Discounts') }}</td><td class="num">{{ Money::format($report['discount']) }}</td></tr>
            <tr><td>{{ __('Tax') }}</td><td class="num">{{ Money::format($report['tax']) }}</td></tr>
            <tr><td>{{ __('Delivery') }}</td><td class="num">{{ Money::format($report['delivery']) }}</td></tr>
            <tr><td>{{ __('Returns') }} ({{ Number::format($report['returns_count']) }})</td><td class="num">{{ Money::format($report['returns_total']) }}</td></tr>
            <tr class="total"><td>{{ __('Net Revenue') }}</td><td class="num">{{ Money::format($report['net_revenue']) }}</td></tr>
        </tbody>
    </table>

    @if (! empty($report['payments']))
        <h2 style="font-size:14px;margin-top:20px;">{{ __('Payments by Method') }}</h2>
        <table>
            <thead><tr><th>{{ __('Method') }}</th><th style="text-align:right">{{ __('Amount') }}</th></tr></thead>
            <tbody>
                @foreach ($report['payments'] as $method => $amount)
                    <tr><td>{{ PaymentMethod::tryFrom($method)?->label() ?? ucfirst($method) }}</td><td class="num">{{ Money::format($amount) }}</td></tr>
                @endforeach
            </tbody>
        </table>
    @endif
</body>
</html>
