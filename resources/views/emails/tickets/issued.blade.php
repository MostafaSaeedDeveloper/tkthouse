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
    @php
      $lbl = 'padding:11px 0 11px 18px;font-size:11px;letter-spacing:.6px;text-transform:uppercase;color:#8a8aa3;vertical-align:top;border-bottom:1px solid #26263a;';
      $val = 'padding:11px 18px 11px 12px;font-size:14px;color:#dddde8;text-align:right;vertical-align:top;border-bottom:1px solid #26263a;';
    @endphp
    {{-- A real table: Gmail and other mail apps ignore flexbox, which glued labels to their values. --}}
    <table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0" style="background:#15151e;border:1px solid #26263a;border-radius:10px;border-collapse:separate;margin:20px 0;">
      <tr>
        <td style="{{ $lbl }}" width="42%">Invoice</td>
        <td style="{{ $val }}color:#f5b800;font-weight:700;">{{ $order->invoice_number }}</td>
      </tr>
      <tr>
        <td style="{{ $lbl }}">Invoice date</td>
        <td style="{{ $val }}">{{ $order->invoice_issued_at?->format('d M Y') }}</td>
      </tr>
      <tr>
        <td style="{{ $lbl }}">Payment method</td>
        <td style="{{ $val }}">{{ $invoice['payment_method'] ?? $order->payment_method }}</td>
      </tr>
      @foreach($order->items as $item)
        <tr>
          <td style="{{ $lbl }}text-transform:none;letter-spacing:0;font-size:13px;color:#dddde8;">{{ $item->ticket_name }}&nbsp;<span style="color:#8a8aa3;white-space:nowrap;">×&nbsp;{{ $item->quantity }}</span></td>
          <td style="{{ $val }}white-space:nowrap;">{{ $money($item->line_total) }}</td>
        </tr>
      @endforeach
      @if(($invoice['discount'] ?? 0) > 0)
        <tr>
          <td style="{{ $lbl }}">Discount</td>
          <td style="{{ $val }}white-space:nowrap;">-{{ $money($invoice['discount']) }}</td>
        </tr>
      @endif
      @foreach($invoice['fees'] ?? [] as $fee)
        <tr>
          <td style="{{ $lbl }}">{{ $fee['name'] }}@if($fee['fee_type'] === 'percentage') ({{ rtrim(rtrim(number_format($fee['value'], 2), '0'), '.') }}%)@endif</td>
          <td style="{{ $val }}white-space:nowrap;">{{ $money($fee['amount']) }}</td>
        </tr>
      @endforeach
      <tr>
        <td style="padding:14px 0 14px 18px;font-size:12px;letter-spacing:.6px;text-transform:uppercase;color:#b8b8cc;font-weight:700;">Total paid</td>
        <td style="padding:14px 18px 14px 12px;font-size:17px;color:#ffffff;font-weight:700;text-align:right;white-space:nowrap;">{{ $money($invoice['total']) }}</td>
      </tr>
    </table>
  @endif

  @php
    $dt = 'padding:11px 0 11px 18px;font-size:11px;letter-spacing:.6px;text-transform:uppercase;color:#8a8aa3;vertical-align:top;border-bottom:1px solid #26263a;';
    $dv = 'padding:11px 18px 11px 12px;font-size:14px;color:#dddde8;text-align:right;vertical-align:top;border-bottom:1px solid #26263a;';
    $ticketCount = $order->issuedTickets->count();
  @endphp

  <p class="ep" style="margin-top:22px;margin-bottom:8px;"><strong>Your booking</strong></p>
  <table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0" style="background:#15151e;border:1px solid #26263a;border-radius:10px;border-collapse:separate;margin:0 0 6px;">
    @if($event)
      <tr><td style="{{ $dt }}" width="34%">Event</td><td style="{{ $dv }}font-weight:700;color:#fff;">{{ $event->name }}</td></tr>
      <tr><td style="{{ $dt }}">Date &amp; time</td><td style="{{ $dv }}">{{ $event->formatted_date_range }}@if($event->event_time) · {{ \Carbon\Carbon::parse($event->event_time)->format('g:i A') }}@endif</td></tr>
      @if($event->location)<tr><td style="{{ $dt }}">Venue</td><td style="{{ $dv }}">{{ $event->location }}</td></tr>@endif
    @endif
    <tr><td style="{{ $dt }}">Order</td><td style="{{ $dv }}color:#f5b800;font-weight:700;">#{{ $order->order_number }}</td></tr>
    <tr><td style="padding:11px 0 11px 18px;font-size:11px;letter-spacing:.6px;text-transform:uppercase;color:#8a8aa3;vertical-align:top;">Tickets</td><td style="padding:11px 18px 11px 12px;font-size:14px;color:#dddde8;text-align:right;">{{ $ticketCount }} {{ \Illuminate\Support\Str::plural('ticket', $ticketCount) }}</td></tr>
  </table>

  <div class="ecta-wrap" style="margin-top:28px;">
    <a class="ecta" href="{{ $ticketsUrl }}" style="text-transform:uppercase;letter-spacing:.6px;">View your tickets</a>
    <p class="ecta-sub">Your digital tickets are in your TKT House account. You may be asked to confirm your email with a one-time code.</p>
  </div>

  <div class="ealert gold" style="margin-top:22px;">
    <strong>Your QR code is not in this email.</strong> It appears on your ticket page when the event team makes it available — you will see it there, ready for the gate.
  </div>

  <div class="ealert green">
    Your invoice (PDF) is attached. You can also download it any time from My Orders in your account.
  </div>
@endsection
