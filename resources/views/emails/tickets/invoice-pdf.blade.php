@php
    $money = fn ($n) => number_format((float) $n, 2).' '.($invoice['currency'] ?? 'EGP');
    $seller = $invoice['seller'] ?? [];
    $tax = $invoice['tax'] ?? ['enabled' => false];
    $customer = $order->customer;
@endphp
<!doctype html>
<html>
<head>
    <meta charset="utf-8">
    <style>
        @page { margin: 36px 40px; }
        body { font-family: DejaVu Sans, sans-serif; color:#111; font-size: 12px; }
        h1 { font-size: 22px; margin: 0 0 4px; }
        .muted { color:#666; }
        .logo img { width: 130px; height: auto; }
        table { width:100%; border-collapse: collapse; }
        .head td { vertical-align: top; }
        .meta td { padding: 2px 0; }
        .items { margin-top: 22px; }
        .items th { background:#111; color:#fff; padding:8px; text-align:left; font-size: 11px; }
        .items td { border-bottom:1px solid #ddd; padding:8px; }
        .r { text-align:right; }
        .totals { width: 55%; margin-left: 45%; margin-top: 14px; }
        .totals td { padding: 5px 8px; }
        .totals .grand td { border-top: 2px solid #111; font-size: 14px; font-weight: bold; }
        .badge { display:inline-block; padding:3px 10px; background:#e7f6ec; color:#17743a; font-weight:bold; font-size:11px; }
        .foot { margin-top: 30px; font-size: 10px; color:#777; text-align:center; }
    </style>
</head>
<body>
    <table class="head">
        <tr>
            <td>
                <div class="logo"><img src="{{ public_path('images/logo-light.png') }}" alt="{{ $seller['name'] ?? 'TKT House' }}"></div>
                <p style="margin:10px 0 0;"><strong>{{ $seller['name'] ?? 'TKT House' }}</strong></p>
                @if(!empty($seller['address']))<p class="muted" style="margin:2px 0;">{{ $seller['address'] }}</p>@endif
                @if(!empty($seller['tax_number']))<p class="muted" style="margin:2px 0;">Tax registration no.: {{ $seller['tax_number'] }}</p>@endif
            </td>
            <td class="r">
                <h1>{{ !empty($tax['enabled']) ? 'Tax Invoice' : 'Invoice' }}</h1>
                <table class="meta" style="width:auto; margin-left:auto;">
                    <tr><td class="muted">Invoice no.</td><td class="r"><strong>{{ $order->invoice_number }}</strong></td></tr>
                    <tr><td class="muted">Invoice date</td><td class="r">{{ $order->invoice_issued_at?->format('d M Y') }}</td></tr>
                    <tr><td class="muted">Order no.</td><td class="r">#{{ $order->order_number }}</td></tr>
                    <tr><td class="muted">Payment method</td><td class="r">{{ $invoice['payment_method'] ?? $order->payment_method }}</td></tr>
                </table>
                <p style="margin:8px 0 0;"><span class="badge">PAID</span></p>
            </td>
        </tr>
    </table>

    <p style="margin:24px 0 2px;" class="muted">Billed to</p>
    <p style="margin:0;"><strong>{{ $customer?->full_name ?: 'Customer' }}</strong></p>
    @if($customer?->email)<p style="margin:2px 0;" class="muted">{{ $customer->email }}</p>@endif
    @if($customer?->phone)<p style="margin:2px 0;" class="muted">{{ $customer->phone }}</p>@endif

    <table class="items">
        <thead>
            <tr>
                <th>Item</th>
                <th class="r">Qty</th>
                <th class="r">Unit price</th>
                <th class="r">Line total</th>
            </tr>
        </thead>
        <tbody>
            @foreach($order->items as $item)
                <tr>
                    <td>{{ $item->ticket_name }}</td>
                    <td class="r">{{ $item->quantity }}</td>
                    <td class="r">{{ $money($item->ticket_price) }}</td>
                    <td class="r">{{ $money($item->line_total) }}</td>
                </tr>
            @endforeach
        </tbody>
    </table>

    <table class="totals">
        <tr><td>Subtotal</td><td class="r">{{ $money($invoice['subtotal'] ?? 0) }}</td></tr>
        @if(($invoice['discount'] ?? 0) > 0)
            <tr><td>Discount @if(!empty($invoice['promo_code']))({{ $invoice['promo_code'] }})@endif</td><td class="r">-{{ $money($invoice['discount']) }}</td></tr>
        @endif
        @if(($invoice['fees'] ?? 0) > 0)
            <tr><td>Booking fees</td><td class="r">{{ $money($invoice['fees']) }}</td></tr>
        @endif
        @if(!empty($tax['enabled']))
            <tr><td>Net amount (excl. {{ $tax['label'] }})</td><td class="r">{{ $money($invoice['net']) }}</td></tr>
            <tr><td>{{ $tax['label'] }} {{ rtrim(rtrim(number_format((float) $tax['rate'], 2), '0'), '.') }}%</td><td class="r">{{ $money($invoice['tax_amount']) }}</td></tr>
        @endif
        <tr class="grand"><td>Total paid</td><td class="r">{{ $money($invoice['total']) }}</td></tr>
    </table>

    @if(!empty($tax['enabled']))
        <p class="muted" style="margin-top:10px; font-size:10px;">All prices include {{ $tax['label'] }}.</p>
    @endif

    <p class="foot">Thank you for your purchase. This invoice was generated automatically and is valid without a signature.</p>
</body>
</html>
