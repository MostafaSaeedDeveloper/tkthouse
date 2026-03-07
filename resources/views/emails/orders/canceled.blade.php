@extends('emails.layouts.base', [
  'title' => 'Order Canceled — TKT House',
  'heroIcon' => '🚫',
  'heroTitle' => 'Your Order Has Been Canceled',
  'heroText' => "Your order was canceled successfully.<br>If this happened by mistake, contact support and we’ll help you immediately.",
  'footerText' => 'This email was sent regarding a cancellation update for your TKT House order.',
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

    $fromLabel = $statusLabels[$oldStatus] ?? str($oldStatus)->replace('_', ' ')->title();
  @endphp

  <p class="ep">Hi <strong>{{ $order->customer->full_name ?: 'there' }}</strong>,</p>

  <p class="ep">
    We wanted to inform you that order <strong>#{{ $order->order_number }}</strong>
    is now marked as <strong style="color:#f87171;">Canceled</strong>.
  </p>

  <div class="einfo">
    <div class="einfo-row">
      <span class="einfo-label">Order</span>
      <span class="einfo-val gold">#{{ $order->order_number }}</span>
    </div>
    <div class="einfo-row">
      <span class="einfo-label">Previous Status</span>
      <span class="einfo-val">{{ $fromLabel }}</span>
    </div>
    <div class="einfo-row">
      <span class="einfo-label">Current Status</span>
      <span class="einfo-val red">✕ &nbsp;Canceled</span>
    </div>
  </div>

  <div class="ealert red">
    If this cancellation is unexpected, please reply to this email and our team will assist you quickly.
  </div>

  <p class="ep ep-sm">
    For any help, mention order number
    <strong style="color:#dddde8;">#{{ $order->order_number }}</strong>
    when contacting support.
  </p>
@endsection
