@extends('emails.layouts.base', [
  'title' => 'Your Ticket Is Ready — TKT House',
  'heroIcon' => '🎫',
  'heroTitle' => 'Your Ticket Is Ready',
  'heroText' => 'A ticket was booked for you. Open it from your TKT House account.',
  'footerText' => 'This email was sent to you as a ticket holder on TKT House.',
])

@section('content')
  @php
    $dt = 'padding:11px 0 11px 18px;font-size:11px;letter-spacing:.6px;text-transform:uppercase;color:#8a8aa3;vertical-align:top;border-bottom:1px solid #26263a;';
    $dv = 'padding:11px 18px 11px 12px;font-size:14px;color:#dddde8;text-align:right;vertical-align:top;border-bottom:1px solid #26263a;';
    $first = $tickets->first();
  @endphp
  <p class="ep">Hi <strong>{{ $first?->holder_name ?: 'there' }}</strong>,</p>
  <p class="ep">{{ $tickets->count() === 1 ? 'A ticket' : $tickets->count().' tickets' }} {{ $tickets->count() === 1 ? 'has' : 'have' }} been issued in your name.</p>

  <table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0" style="background:#15151e;border:1px solid #26263a;border-radius:10px;border-collapse:separate;margin:18px 0 6px;">
    @if($event)
      <tr><td style="{{ $dt }}" width="34%">Event</td><td style="{{ $dv }}font-weight:700;color:#fff;">{{ $event->name }}</td></tr>
      <tr><td style="{{ $dt }}">Date &amp; time</td><td style="{{ $dv }}">{{ $event->formatted_date_range }}@if($event->event_time) · {{ \Carbon\Carbon::parse($event->event_time)->format('g:i A') }}@endif</td></tr>
      @if($event->location)<tr><td style="{{ $dt }}">Venue</td><td style="{{ $dv }}">{{ $event->location }}</td></tr>@endif
    @endif
    @foreach($tickets as $ticket)
      <tr><td style="{{ $loop->last ? 'padding:11px 0 11px 18px;font-size:11px;letter-spacing:.6px;text-transform:uppercase;color:#8a8aa3;' : $dt }}">{{ $loop->first ? 'Ticket' : '' }}</td><td style="{{ $loop->last ? 'padding:11px 18px 11px 12px;font-size:14px;color:#dddde8;text-align:right;' : $dv }}">{{ $ticket->ticket_name }}</td></tr>
    @endforeach
  </table>

  <div class="ecta-wrap" style="margin-top:28px;">
    <a class="ecta" href="{{ $ticketsUrl }}" style="text-transform:uppercase;letter-spacing:.6px;">View my {{ $tickets->count() === 1 ? 'ticket' : 'tickets' }}</a>
    <p class="ecta-sub">No account needed: confirm this email address with a one-time code and your ticket opens.</p>
  </div>

  <div class="ealert gold" style="margin-top:22px;">
    <strong>Your QR code is not in this email.</strong> It appears on your ticket page when the event team makes it available.
  </div>
@endsection
