@php $money = fn ($n) => number_format((float) $n, 2).' EGP'; @endphp
@extends('emails.layouts.base', [
  'title' => 'Your Order Is Complete — TKT House',
  'heroIcon' => '🧾',
  'heroTitle' => 'Order Complete — Your Invoice & Tickets',
  'heroText' => 'Your payment is confirmed. Your invoice and tickets are below.',
  'footerText' => 'This email was sent because your ticket order was completed on TKT House.',
])

@section('content')
  <p class="ep">Hi <strong>{{ $order->customer->full_name ?: 'Customer' }}</strong>,</p>
  <p class="ep">
    Your order <strong>#{{ $order->order_number }}</strong> is paid and your tickets are now generated.
  </p>

  @if($invoice)
    <div class="einfo">
      <div class="einfo-row">
        <span class="einfo-label">Invoice</span>
        <span class="einfo-val gold">{{ $order->invoice_number }}</span>
      </div>
      <div class="einfo-row">
        <span class="einfo-label">Invoice date</span>
        <span class="einfo-val">{{ $order->invoice_issued_at?->format('d M Y') }}</span>
      </div>
      <div class="einfo-row">
        <span class="einfo-label">Payment Method</span>
        <span class="einfo-val">{{ $invoice['payment_method'] ?? $order->payment_method }}</span>
      </div>
      @foreach($order->items as $item)
        <div class="einfo-row">
          <span class="einfo-label">{{ $item->ticket_name }} × {{ $item->quantity }}</span>
          <span class="einfo-val">{{ $money($item->line_total) }}</span>
        </div>
      @endforeach
      @if(($invoice['discount'] ?? 0) > 0)
        <div class="einfo-row">
          <span class="einfo-label">Discount</span>
          <span class="einfo-val">-{{ $money($invoice['discount']) }}</span>
        </div>
      @endif
      @if(($invoice['fees'] ?? 0) > 0)
        <div class="einfo-row">
          <span class="einfo-label">Booking fees</span>
          <span class="einfo-val">{{ $money($invoice['fees']) }}</span>
        </div>
      @endif
      @if(!empty($invoice['tax']['enabled']))
        <div class="einfo-row">
          <span class="einfo-label">Net amount</span>
          <span class="einfo-val">{{ $money($invoice['net']) }}</span>
        </div>
        <div class="einfo-row">
          <span class="einfo-label">{{ $invoice['tax']['label'] }} {{ rtrim(rtrim(number_format((float) $invoice['tax']['rate'], 2), '0'), '.') }}%</span>
          <span class="einfo-val">{{ $money($invoice['tax_amount']) }}</span>
        </div>
      @endif
      <div class="einfo-row">
        <span class="einfo-label">Total Paid</span>
        <span class="einfo-val" style="color:#fff;font-weight:700;">{{ $money($invoice['total']) }}</span>
      </div>
    </div>
  @endif

  <p class="ep">You can view or download each ticket from the links below.</p>

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
    Attached: your invoice (PDF) and a PDF containing all issued tickets.
    You can also download the invoice any time from your TKT House account under My Orders.
  </div>
@endsection
