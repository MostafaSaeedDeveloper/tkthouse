@extends('emails.layouts.base', [
  'title' => 'Payment Window Expired — TKT House',
  'heroIcon' => '⌛',
  'heroTitle' => 'Payment Time Expired',
  'heroText' => "Your order had been approved, but payment was not completed within the allowed time window.",
  'footerText' => 'This email was sent regarding your approved booking request on TKT House.',
])

@section('content')
  <p class="ep">Hi <strong>{{ $order->customer->full_name ?: 'there' }}</strong>,</p>

  <p class="ep">
    Your order was successfully approved for payment, however the payment was not completed within the allowed time window.
    As a result, the order has been automatically canceled.
  </p>

  <div class="einfo">
    <div class="einfo-row">
      <span class="einfo-label">Order</span>
      <span class="einfo-val gold">#{{ $order->order_number }}</span>
    </div>
    <div class="einfo-row">
      <span class="einfo-label">Current Status</span>
      <span class="einfo-val red">✕ &nbsp;Canceled</span>
    </div>
    <div class="einfo-row">
      <span class="einfo-label">Reason</span>
      <span class="einfo-val">Payment deadline expired</span>
    </div>
  </div>

  <div class="ealert red">
    To book again, please contact the administration team so they can help you reopen the booking or create a new reservation.
  </div>

  <p class="ep ep-sm">
    Please mention order number
    <strong style="color:#dddde8;">#{{ $order->order_number }}</strong>
    when contacting support.
  </p>
@endsection
