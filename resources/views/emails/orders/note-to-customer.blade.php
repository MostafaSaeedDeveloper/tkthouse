@extends('emails.layouts.base', [
  'title' => 'Order Update — TKT House',
  'heroIcon' => '📩',
  'heroTitle' => 'New Update on Your Order',
  'heroText' => "Our team shared a new note about your order.<br>Please review the details below.",
  'footerText' => 'This email was sent as an order update from TKT House.',
])

@section('content')
  <p class="ep">Hi <strong>{{ $order->customer->full_name ?: 'there' }}</strong>,</p>

  <p class="ep">
    We added a new update to your order
    <strong>#{{ $order->order_number }}</strong>.
  </p>

  <div class="einfo">
    <div class="einfo-row">
      <span class="einfo-label">Order</span>
      <span class="einfo-val gold">#{{ $order->order_number }}</span>
    </div>
    <div class="einfo-row">
      <span class="einfo-label">Current Status</span>
      <span class="einfo-val">{{ ucwords(str_replace('_', ' ', (string) $order->status)) }}</span>
    </div>
  </div>

  <div class="ealert gold">
    {{ $noteBody }}
  </div>

  <p class="ep ep-sm">
    If you need help, reply to this email and include your order number
    <strong style="color:#dddde8;">#{{ $order->order_number }}</strong>.
  </p>
@endsection
