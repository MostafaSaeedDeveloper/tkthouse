{{-- emails/approved.blade.php — Sent when admin approves order, contains payment link --}}
<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width,initial-scale=1.0">
<title>Your Booking is Approved — TKT House</title>
@include('emails.partials.styles')
</head>
<body>
<div class="eb">
<div class="ew">

  {{-- Logo --}}
  <div class="eh">
    <span class="logo"><span class="g">tkt</span><span class="w">house</span></span>
  </div>

  {{-- Hero --}}
  <div class="ehero">
    <span class="ehero-icon">✅</span>
    <h1>Your Booking is Approved!</h1>
    <p>Great news — your order has been reviewed and approved.<br>
       Complete your payment to secure your tickets.</p>
  </div>

  {{-- Body --}}
  <div class="ebody">

    <p class="ep">Hi <strong>{{ $order->customer->full_name ?: 'there' }}</strong>,</p>
    <p class="ep">
      We're excited to let you know that your booking request has been
      reviewed and <strong>approved</strong> by our team. Your spot is reserved —
      all you need to do now is complete the payment below before the link expires.
    </p>

    {{-- Order info --}}
    <div class="einfo">
      <div class="einfo-row">
        <span class="einfo-label">Order</span>
        <span class="einfo-val gold">#{{ $order->order_number }}</span>
      </div>
      <div class="einfo-row">
        <span class="einfo-label">Amount Due</span>
        <span class="einfo-val" style="color:#fff;font-weight:700;">{{ number_format($order->total_amount, 2) }} EGP</span>
      </div>
      <div class="einfo-row">
        <span class="einfo-label">Status</span>
        <span class="einfo-val green">✓ Approved</span>
      </div>
    </div>

    {{-- CTA --}}
    <div class="ecta-wrap">
      <a href="{{ $paymentLink }}" class="ecta">💳 &nbsp;Pay Now & Confirm Tickets</a>
      <p class="ecta-sub">Secure payment · Takes less than 2 minutes</p>
    </div>

    {{-- Warning --}}
    <div class="ealert gold">
      ⚠️ &nbsp;This payment link is personal and time-limited. Please do not share it with anyone else.
    </div>

    {{-- Fallback URL --}}
    <p class="ep ep-sm">If the button above doesn't work, copy and paste this link into your browser:</p>
    <div class="eurl"><a href="{{ $paymentLink }}">{{ $paymentLink }}</a></div>

    <hr class="ediv">

    <p class="ep ep-sm">
      Questions about your order? Reply to this email and mention
      order <strong style="color:#dddde8;">#{{ $order->order_number }}</strong> —
      our team is happy to help.
    </p>

  </div>

  {{-- Footer --}}
  <div class="efooter">
    <p>© {{ date('Y') }} TKT House · All rights reserved</p>
    <p style="margin-top:5px;">This email was sent because you placed a booking on TKT House.</p>
  </div>

</div>
</div>
</body>
</html>
