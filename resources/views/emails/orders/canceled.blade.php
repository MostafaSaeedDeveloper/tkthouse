@extends('emails.layouts.base', [
  'title' => 'Payment Time Expired — TKT House',
  'heroIcon' => '⌛',
  'heroTitle' => 'Payment Time Expired',
  'heroText' => "Your order had been approved, but payment was not completed within the allowed time.",
  'footerText' => 'This email was sent regarding your approved booking request on TKT House.',
])

@section('content')
  <p class="ep">Hi <strong>{{ $order->customer->full_name ?: 'there' }}</strong>,</p>

  <p class="ep">
    Your order was successfully approved for payment, however the payment was not completed within the allowed time.
    As a result, the order has been automatically canceled.
  </p>

  @include('emails.partials.info-table', ['rows' => [
    ['label' => 'Order', 'value' => '#'.$order->order_number, 'tone' => 'gold'],
    ['label' => 'Current Status', 'value' => '✕ Canceled', 'tone' => 'red'],
    ['label' => 'Reason', 'value' => 'Payment deadline expired'],
  ]])
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
