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

  @include('emails.partials.info-table', ['rows' => [
    ['label' => 'Order', 'value' => '#'.$order->order_number, 'tone' => 'gold'],
    ['label' => 'Current Status', 'value' => ucwords(str_replace('_', ' ', (string) $order->status))],
  ]])
  </div>

  <div class="ealert gold">
    {{ $noteBody }}
  </div>

  <p class="ep ep-sm">
    If you need help, reply to this email and include your order number
    <strong style="color:#dddde8;">#{{ $order->order_number }}</strong>.
  </p>
@endsection
