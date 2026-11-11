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

  @include('emails.partials.info-table', ['rows' => [
    ['label' => 'Order', 'value' => '#'.$order->order_number, 'tone' => 'gold'],
    ['label' => 'Current Status', 'value' => '✕ Rejected', 'tone' => 'red'],
    ['label' => 'Reason', 'value' => 'Booking request not approved for this event'],
  ]])
  </div>

  <div class="ealert red">
    If you need help, please contact the administration team for more details or available alternatives.
  </div>

  <p class="ep ep-sm">
    When contacting support, please include order number
    <strong style="color:#dddde8;">#{{ $order->order_number }}</strong>.
  </p>
@endsection
