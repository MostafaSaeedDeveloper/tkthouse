@extends('emails.layouts.base', [
  'title' => 'Order Canceled — TKT House',
  'heroIcon' => '🚫',
  'heroTitle' => 'Your Order Has Been Canceled',
  'heroText' => "Your order was canceled because the payment window has expired.<br>If needed, you can place a new order anytime.",
  'footerText' => 'This email was sent regarding a cancellation update for your TKT House order.',
])

@section('content')
  <p class="ep">Hi <strong>{{ $order->customer->full_name ?: 'there' }}</strong>,</p>

  <p class="ep">
    We wanted to inform you that order <strong>#{{ $order->order_number }}</strong>
    was canceled because the <strong style="color:#f87171;">payment deadline expired</strong>.
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
      <span class="einfo-val">Payment time limit expired</span>
    </div>
  </div>

  <div class="ealert red">
    Cancellation reason: payment window expired before we received the payment confirmation.
  </div>

  <p class="ep ep-sm">
    For any help, mention order number
    <strong style="color:#dddde8;">#{{ $order->order_number }}</strong>
    when contacting support.
  </p>
@endsection
