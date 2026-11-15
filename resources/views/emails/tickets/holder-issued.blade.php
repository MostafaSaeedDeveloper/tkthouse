@extends('emails.layouts.base', [
  'title' => 'Your Tickets Are Issued — TKT House',
  'heroIcon' => '✅',
  'heroTitle' => 'Your Tickets Have Been Issued',
  'heroText' => 'Your tickets are ready. You can access them directly from the links below.',
  'footerText' => 'This email was sent to you as a ticket holder on TKT House.',
])

@section('content')
  <p class="ep">Hello,</p>
  <p class="ep">Your tickets for order <strong>#{{ $order->order_number }}</strong> have been issued successfully.</p>

  @foreach($tickets as $ticket)
    <div class="eticket">
      <p class="eticket-num">{{ $ticket->ticket_number }}</p>
      <p class="eticket-name">{{ $ticket->ticket_name }}</p>
      <p class="eticket-meta">Holder: {{ $ticket->holder_name }} · {{ $ticket->holder_email }}</p>
      <div class="eticket-links">
        <a href="{{ route('front.tickets.show', $ticket) }}" style="display:inline-block;background:#f5b800;color:#111;font-weight:700;text-decoration:none;padding:10px 20px;border-radius:8px;">View ticket</a>
      </div>
    </div>
  @endforeach

  <div class="ealert blue">
    Tap “View ticket” and sign in with this email to see your ticket and its QR code.
  </div>
@endsection
