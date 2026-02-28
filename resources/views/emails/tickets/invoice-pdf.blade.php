<!doctype html>
<html>
<head>
    <meta charset="utf-8">
    <style>
        body { font-family: DejaVu Sans, sans-serif; color:#111; }
        table { width:100%; border-collapse: collapse; margin-top: 12px; }
        th,td { border:1px solid #ccc; padding:8px; text-align:left; }
    </style>
</head>
<body>
    <h2>Invoice - Order #{{ $order->order_number }}</h2>
    <p><strong>Customer:</strong> {{ $order->customer->full_name }}</p>
    <p><strong>Email:</strong> {{ $order->customer->email }}</p>
    <p><strong>Payment Method:</strong> {{ $order->payment_method }}</p>

    <table>
        <thead>
            <tr>
                <th>Item</th>
                <th>Qty</th>
                <th>Unit Price</th>
                <th>Line Total</th>
            </tr>
        </thead>
        <tbody>
            @foreach($order->items as $item)
                <tr>
                    <td>{{ $item->ticket_name }}</td>
                    <td>{{ $item->quantity }}</td>
                    <td>{{ number_format($item->ticket_price, 2) }} EGP</td>
                    <td>{{ number_format($item->line_total, 2) }} EGP</td>
                </tr>
            @endforeach
        </tbody>
    </table>

    <h3>Total: {{ number_format($order->total_amount, 2) }} EGP</h3>
</body>
</html>
