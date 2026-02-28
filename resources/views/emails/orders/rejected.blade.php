@extends('emails.layouts.base', [
  'title' => 'Booking Update — TKT House',
  'heroIcon' => '❌',
  'heroTitle' => "We Couldn't Approve Your Booking",
  'heroText' => "Unfortunately your booking request was not approved this time.<br>We're sorry for any inconvenience caused.",
  'footerText' => 'This email was sent regarding your booking request on TKT House.',
])

@section('content')
  <p class="ep">Hi <strong>{{ $order->customer->full_name ?: 'there' }}</strong>,</p>
  <p class="ep">
    After reviewing your booking request for order
    <strong>#{{ $order->order_number }}</strong>, we were unfortunately
    unable to approve it at this time. This can happen due to limited
    availability or other booking conditions.
  </p>

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
@endsection
