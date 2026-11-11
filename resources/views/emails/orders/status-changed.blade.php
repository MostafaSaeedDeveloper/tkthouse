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

  @include('emails.partials.info-table', ['rows' => [
    ['label' => 'Order', 'value' => '#'.$order->order_number, 'tone' => 'gold'],
    ['label' => 'Current Status', 'value' => (string) $toLabel, 'color' => '#41d394'],
  ]])
  </div>

  <p class="ep ep-sm">
    If you have any questions, reply to this email and mention your order number
    <strong style="color:#dddde8;">#{{ $order->order_number }}</strong>.
  </p>
@endsection
