@extends('emails.layouts.base', [
  'title' => 'Booking Request Not Approved — TKT House',
  'heroIcon' => '🚫',
  'heroTitle' => 'Booking Request Not Approved',
  'heroText' => "Your request was reviewed, but it could not be approved for this event.",
  'footerText' => 'This email was sent regarding your booking request on TKT House.',
])

@section('content')
  <p class="ep">Hi <strong>{{ $order->customer->full_name ?: 'there' }}</strong>,</p>

  <p class="ep">
    We reviewed your booking request for order <strong>#{{ $order->order_number }}</strong>,
    and unfortunately it was <strong style="color:#f87171;">not approved</strong>
    for this event.
  </p>

  <div class="einfo">
    <div class="einfo-row">
      <span class="einfo-label">Order</span>
      <span class="einfo-val gold">#{{ $order->order_number }}</span>
    </div>
    <div class="einfo-row">
      <span class="einfo-label">Current Status</span>
      <span class="einfo-val red">✕ &nbsp;Rejected</span>
    </div>
    <div class="einfo-row">
      <span class="einfo-label">Reason</span>
      <span class="einfo-val">Booking request not approved for this event</span>
    </div>
  </div>

  <div class="ealert red">
    If you need help, please contact the administration team for more details or available alternatives.
  </div>

  <p class="ep ep-sm">
    When contacting support, please include order number
    <strong style="color:#dddde8;">#{{ $order->order_number }}</strong>.
  </p>
@endsection
