@extends('emails.layouts.base', [
  'title' => 'Your Ticket Is Ready — TKT House',
  'heroIcon' => '🎫',
  'heroTitle' => 'Your Ticket Is Ready',
  'heroText' => 'Your ticket is attached as a PDF and can also be viewed from your secure ticket page.',
  'footerText' => 'This email was sent by TKT House ticketing service.',
])

@section('content')
  <p class="ep">Hi <strong>{{ $ticket->holder_name ?: 'Guest' }}</strong>,</p>
  <p class="ep">
    Your ticket has been issued successfully. We attached the PDF ticket to this email for easy download and check-in.
  </p>

  <div class="einfo">
    <div class="einfo-row">
      <span class="einfo-label">Ticket Number</span>
      <span class="einfo-val gold">{{ $ticket->ticket_number }}</span>
    </div>
    <div class="einfo-row">
      <span class="einfo-label">Ticket Type</span>
      <span class="einfo-val">{{ $ticket->ticketTypeLabel() }}</span>
    </div>
    <div class="einfo-row">
      <span class="einfo-label">Event</span>
      <span class="einfo-val">{{ $ticket->eventLabel() }}</span>
    </div>
    <div class="einfo-row">
      <span class="einfo-label">Order</span>
      <span class="einfo-val">#{{ $ticket->order?->order_number ?: '—' }}</span>
    </div>
    <div class="einfo-row">
      <span class="einfo-label">Sent To</span>
      <span class="einfo-val">{{ $recipientEmail }}</span>
    </div>
  </div>

  <div class="ecta-wrap">
    <a class="ecta" href="{{ $showUrl }}">View Ticket</a>
    <p class="ecta-sub">If the button does not work, use the link below.</p>
  </div>

  <div class="eurl"><a href="{{ $showUrl }}">{{ $showUrl }}</a></div>

  <div class="ealert blue">
    The PDF attachment includes your ticket QR and details. Please keep it available for entry.
  </div>
@endsection
