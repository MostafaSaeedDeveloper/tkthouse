@extends('emails.layouts.base', [
  'title' => 'Payment Confirmation — TKT House',
  'heroIcon' => '💰',
  'heroTitle' => 'Payment Received Successfully',
  'heroText' => 'Thanks for your payment. Your invoice details are shown below.',
  'footerText' => 'This email confirms your payment on TKT House.',
])

@section('content')
  <p class="ep">Hi <strong>{{ $order->customer->full_name ?: 'Customer' }}</strong>,</p>
  <p class="ep">Your payment was completed successfully for order <strong>#{{ $order->order_number }}</strong>.</p>

  <div class="einfo">
    <div class="einfo-row">
      <span class="einfo-label">Order</span>
      <span class="einfo-val gold">#{{ $order->order_number }}</span>
    </div>
    <div class="einfo-row">
      <span class="einfo-label">Total Paid</span>
      <span class="einfo-val" style="color:#fff;font-weight:700;">{{ number_format($order->total_amount, 2) }} EGP</span>
    </div>
    <div class="einfo-row">
      <span class="einfo-label">Payment Method</span>
      <span class="einfo-val">{{ $order->payment_method }}</span>
    </div>
  </div>

  <div class="ealert green">
    An invoice PDF is attached to this email.
  </div>
@endsection
