@extends('emails.layouts.base', [
  'title' => \App\Mail\OrderInvoicePaidMail::SUBJECT,
  'heroIcon' => '✅',
  'heroTitle' => 'Your Order Is Confirmed',
  'heroText' => 'Your payment was successful. Your invoice is attached as a PDF.',
  'footerText' => 'This email confirms your payment on TKT House.',
])

@section('content')
  <p class="ep">Hi <strong>{{ $invoice->customerName }}</strong>,</p>
  <p class="ep">Thank you! Your payment for order <strong>#{{ $invoice->orderNumber }}</strong> went through and your booking is confirmed.</p>

  <div class="einfo">
    <div class="einfo-row"><span class="einfo-label">Status</span><span class="einfo-val green">PAID</span></div>
    <div class="einfo-row"><span class="einfo-label">Order</span><span class="einfo-val gold">#{{ $invoice->orderNumber }}</span></div>
    <div class="einfo-row"><span class="einfo-label">Invoice</span><span class="einfo-val">{{ $invoice->number }}</span></div>
    <div class="einfo-row"><span class="einfo-label">Event</span><span class="einfo-val">{{ $invoice->event['name'] }}</span></div>
    @if($invoice->event['date'])
      <div class="einfo-row"><span class="einfo-label">Date</span><span class="einfo-val">{{ collect([$invoice->event['date'], $invoice->event['time']])->filter()->implode(' · ') }}</span></div>
    @endif
    <div class="einfo-row"><span class="einfo-label">Payment Method</span><span class="einfo-val">{{ $invoice->paymentMethod }}</span></div>
    @if($invoice->paidAt)
      <div class="einfo-row"><span class="einfo-label">Paid On</span><span class="einfo-val">{{ $invoice->paidAt->format('d M Y, H:i') }}</span></div>
    @endif
    <div class="einfo-row"><span class="einfo-label">Total Paid</span><span class="einfo-val" style="color:#fff;font-weight:700;">{{ $invoice->money($invoice->total) }}</span></div>
  </div>

  <div class="ealert green">Your payment invoice (PDF) is attached to this email. Keep it as proof of payment.</div>

  @if($orderUrl)
    <div class="ecta-wrap"><a class="ecta" href="{{ $orderUrl }}">View Your Order</a></div>
    @if($ticketsUrl)<p class="ecta-sub"><a href="{{ $ticketsUrl }}">Go to your tickets</a> · you can also download this invoice from your order page anytime.</p>@endif
  @else
    <p class="ep ep-sm">Your tickets are sent in a separate email.</p>
  @endif
@endsection
