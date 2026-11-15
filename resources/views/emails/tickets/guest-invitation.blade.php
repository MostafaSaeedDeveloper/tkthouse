@extends('emails.layouts.base', [
  'title' => 'Guest Invitation — TKT House',
  'heroIcon' => '🎟️',
  'heroTitle' => 'You are on the guest list',
  'heroText' => 'Your invitation is a digital ticket in your TKT House account.',
  'footerText' => 'This invitation was sent by TKT House.',
])

@section('content')
  <p class="ep" style="margin-bottom:16px;">Hello <strong>{{ $guestName }}</strong>,</p>
  <p class="ep" style="margin-bottom:14px;">You have been invited to <strong>{{ $eventName }}</strong>.</p>
  @if($event)
    <p class="ep" style="margin-bottom:14px;">{{ $event->formatted_date_range }}@if($event->event_time) · {{ \Carbon\Carbon::parse($event->event_time)->format('g:i A') }}@endif @if($event->location) — {{ $event->location }}@endif</p>
  @endif

  <div class="ecta-wrap" style="margin-top:26px;">
    <a class="ecta" href="{{ $showUrl }}" style="text-transform:uppercase;letter-spacing:.6px;">View your ticket</a>
    <p class="ecta-sub">Confirm this email address with a one-time code and your ticket opens — no account setup needed.</p>
  </div>

  <div class="ealert gold" style="margin-top:22px;">
    <strong>Your QR code is not in this email.</strong> It appears on your ticket page when the event team makes it available.
  </div>
@endsection
