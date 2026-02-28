{{-- emails/rejected.blade.php — Sent when admin rejects the order --}}
<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width,initial-scale=1.0">
<title>Booking Update — TKT House</title>
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
    <span class="ehero-icon">❌</span>
    <h1>We Couldn't Approve Your Booking</h1>
    <p>Unfortunately your booking request was not approved this time.<br>
       We're sorry for any inconvenience caused.</p>
  </div>

  {{-- Body --}}
  <div class="ebody">

    <p class="ep">Hi <strong>{{ $order->customer->full_name ?: 'there' }}</strong>,</p>
    <p class="ep">
      After reviewing your booking request for order
      <strong>#{{ $order->order_number }}</strong>, we were unfortunately
      unable to approve it at this time. This can happen due to limited
      availability or other booking conditions.
    </p>

    {{-- Order info --}}
    <div class="einfo">
      <div class="einfo-row">
        <span class="einfo-label">Order</span>
        <span class="einfo-val gold">#{{ $order->order_number }}</span>
      </div>
      <div class="einfo-row">
        <span class="einfo-label">Status</span>
        <span class="einfo-val red">✕ &nbsp;Not Approved</span>
      </div>
    </div>

    <div class="ealert red">
      If you believe this is an error or would like more information, please
      don't hesitate to contact us — we'll do our best to help.
    </div>

    {{-- What to do next --}}
    <p class="ep">Here are a few things you can do:</p>
    <ul style="margin: 0 0 16px 18px; padding: 0;">
      <li style="font-size:14px; color:#b8b8cc; line-height:1.75; margin-bottom:6px;">
        Check our website for upcoming events and available tickets.
      </li>
      <li style="font-size:14px; color:#b8b8cc; line-height:1.75; margin-bottom:6px;">
        Contact our support team for clarification about this specific order.
      </li>
      <li style="font-size:14px; color:#b8b8cc; line-height:1.75;">
        Try booking again — availability may change at any time.
      </li>
    </ul>

    <hr class="ediv">

    <p class="ep ep-sm">
      Need help? Reply to this email with your order number
      <strong style="color:#dddde8;">#{{ $order->order_number }}</strong>
      and our support team will get back to you as soon as possible.
    </p>

  </div>

  {{-- Footer --}}
  <div class="efooter">
    <p>© {{ date('Y') }} TKT House · All rights reserved</p>
    <p style="margin-top:5px;">This email was sent regarding your booking request on TKT House.</p>
  </div>

</div>
</div>
</body>
</html>
