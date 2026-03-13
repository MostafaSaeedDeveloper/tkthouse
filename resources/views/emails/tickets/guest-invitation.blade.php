@extends('emails.layouts.base', [
  'title' => 'Guest Invitation — TKT House',
  'heroIcon' => '🎟️',
  'heroTitle' => 'Guest Invitation',
  'heroText' => 'Your invitation ticket is attached as a PDF document.',
  'footerText' => 'This invitation was sent by TKT House.',
])

@section('content')
  <p class="ep" style="margin-bottom:16px;">Hello <strong>{{ $guestName }}</strong>,</p>

  <p class="ep" style="margin-bottom:14px;">
    You have been selected to attend <strong>{{ $eventName }}</strong>.
  </p>

  <p class="ep" style="margin-bottom:14px;">
    Your invitation ticket is attached to this email as a PDF document.
  </p>

  <div class="ealert blue" style="margin-top:6px;">
    Please present the QR code at the entrance to access the event.
  </div>
@endsection
