<p>Hi {{ $order->customer->full_name ?: 'Customer' }},</p>

<p>We are sorry, but your booking request for order <strong>{{ $order->order_number }}</strong> was not approved.</p>

<p>If you need help, please contact support and mention your order number.</p>
