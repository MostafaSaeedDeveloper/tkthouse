@extends('emails.layouts.base', [
  'title' => 'Your Booking is Approved — TKT House',
  'heroIcon' => '✅',
  'heroTitle' => 'Your Booking is Approved!',
  'heroText' => "Great news — your order has been reviewed and approved.<br>Complete your payment to secure your tickets.",
  'footerText' => 'This email was sent because you placed a booking on TKT House.',
])

@section('content')
  <p class="ep">Hi <strong>{{ $order->customer->full_name ?: 'there' }}</strong>,</p>
  <p class="ep">
    We're excited to let you know that your booking request has been
    reviewed and <strong>approved</strong> by our team. Your spot is reserved —
    all you need to do now is complete the payment below before the link expires.
  </p>

  @include('emails.partials.info-table', ['rows' => [
    ['label' => 'Order', 'value' => '#'.$order->order_number, 'tone' => 'gold'],
    ['label' => 'Amount Due', 'value' => number_format($order->total_amount, 2).' EGP', 'tone' => 'white'],
    ['label' => 'Status', 'value' => '✓ Approved', 'tone' => 'green'],
  ]])
  </div>

  <div class="ecta-wrap">
    <a href="{{ $paymentLink }}" class="ecta">💳 &nbsp;Pay Now & Confirm Tickets</a>
    <p class="ecta-sub">Secure payment · Takes less than 2 minutes</p>
  </div>

  <div class="ealert gold">
    ⚠️ &nbsp;This payment link is personal and time-limited. Please do not share it with anyone else.
  </div>

  <p class="ep ep-sm">If the button above doesn't work, copy and paste this link into your browser:</p>
  <div class="eurl"><a href="{{ $paymentLink }}">{{ $paymentLink }}</a></div>

  <hr class="ediv">

  <p class="ep ep-sm">
    Questions about your order? Reply to this email and mention
    order <strong style="color:#dddde8;">#{{ $order->order_number }}</strong> —
    our team is happy to help.
  </p>
@endsection
