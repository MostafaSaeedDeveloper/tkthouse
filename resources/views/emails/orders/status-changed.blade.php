@extends('emails.layouts.base', [
  'title' => 'Order Status Updated — TKT House',
  'heroIcon' => 'ℹ️',
  'heroTitle' => 'Your Order Status Has Changed',
  'heroText' => "We wanted to let you know that your order status has just been updated.",
  'footerText' => 'This email was sent regarding an update to your TKT House order.',
])

@section('content')
  @php
    $statusLabels = [
      'pending_approval' => 'Pending Approval',
      'pending_payment' => 'Pending Payment',
      'on_hold' => 'On Hold',
      'paid' => 'Paid',
      'canceled' => 'Canceled',
      'rejected' => 'Rejected',
      'refunded' => 'Refunded',
      'partially_refunded' => 'Partially Refunded',
    ];

    $toLabel = $statusLabels[$newStatus] ?? str($newStatus)->replace('_', ' ')->title();
  @endphp

  <p class="ep">Hi <strong>{{ $order->customer->full_name ?: 'there' }}</strong>,</p>

  <p class="ep">
    Your order <strong>#{{ $order->order_number }}</strong> status was updated.
  </p>

  <div class="einfo">
    <div class="einfo-row">
      <span class="einfo-label">Order</span>
      <span class="einfo-val gold">#{{ $order->order_number }}</span>
    </div>
    <div class="einfo-row">
      <span class="einfo-label">Current Status</span>
      <span class="einfo-val" style="color:#41d394;font-weight:700;">{{ $toLabel }}</span>
    </div>
  </div>

  <p class="ep ep-sm">
    If you have any questions, reply to this email and mention your order number
    <strong style="color:#dddde8;">#{{ $order->order_number }}</strong>.
  </p>
@endsection
