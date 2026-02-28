<p>Hi {{ $order->customer->full_name ?: 'Customer' }},</p>

<p>Your booking request for order <strong>{{ $order->order_number }}</strong> has been approved.</p>

<p>Please complete your payment using the link below:</p>

<p><a href="{{ $paymentLink }}">Pay now</a></p>

<p>If the button does not work, copy this URL into your browser:</p>
<p>{{ $paymentLink }}</p>
