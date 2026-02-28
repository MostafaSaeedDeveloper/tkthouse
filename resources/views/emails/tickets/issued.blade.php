@extends('emails.layouts.base', [
  'title' => 'Your Tickets Are Ready — TKT House',
  'heroIcon' => '🎟️',
  'heroTitle' => 'Your Tickets Have Been Issued',
  'heroText' => 'Your payment is confirmed and your tickets are now ready.',
  'footerText' => 'This email was sent because your ticket order was completed on TKT House.',
])

@section('content')
  <p class="ep">Hi <strong>{{ $order->customer->full_name ?: 'Customer' }}</strong>,</p>
  <p class="ep">
    Your order <strong>#{{ $order->order_number }}</strong> is paid and your tickets are now generated.
    You can view or download each ticket from the links below.
  </p>

  @foreach($order->issuedTickets as $ticket)
    <div class="eticket">
      <p class="eticket-num">{{ $ticket->ticket_number }}</p>
      <p class="eticket-name">{{ $ticket->ticket_name }}</p>
      <p class="eticket-meta">Holder: {{ $ticket->holder_name }} · {{ $ticket->holder_email }}</p>
      <div class="eticket-links">
        <a href="{{ route('front.tickets.show', $ticket) }}">View</a>
        <a href="{{ route('front.tickets.download', $ticket) }}">PDF</a>
      </div>
    </div>
  @endforeach

  <div class="ealert green">
    A PDF attachment containing all issued tickets is included in this email.
  </div>
@endsection
