<p>Hi {{ $order->customer->full_name ?: 'Customer' }},</p>
<p>Your payment was completed successfully for order <strong>#{{ $order->order_number }}</strong>.</p>
<p>Total Paid: <strong>{{ number_format($order->total_amount, 2) }} EGP</strong></p>
<p>Payment Method: <strong>{{ $order->payment_method }}</strong></p>
<p>An invoice PDF is attached to this email.</p>
