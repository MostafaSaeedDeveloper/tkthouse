@extends('emails.layouts.base', [
  'title' => ($test ? '[TEST] ' : '').'Your QR code is ready — TKT House',
  'heroIcon' => '🎟️',
  'heroTitle' => 'Your QR code is ready',
  'heroText' => $test ? 'TEST — sample e-mail. These QR codes are NOT valid for entry.' : 'Your QR code'.(count($items) > 1 ? 's are' : ' is').' now available. Keep '.(count($items) > 1 ? 'them' : 'it').' ready for the gate.',
  'footerText' => $test ? 'This is a TEST e-mail sent from the TKT House admin. No customer received it.' : 'This email was sent because your ticket QR code became available on TKT House.',
])

@section('content')
  @php
    $dt = 'padding:11px 0 11px 18px;font-size:11px;letter-spacing:.6px;text-transform:uppercase;color:#8a8aa3;vertical-align:top;border-bottom:1px solid #26263a;';
    $dv = 'padding:11px 18px 11px 12px;font-size:14px;color:#dddde8;text-align:right;vertical-align:top;border-bottom:1px solid #26263a;';
  @endphp

  @if($test)
    <div style="background:#3a1010;border:2px solid #e8445a;border-radius:10px;padding:12px 16px;margin:0 0 18px;text-align:center;">
      <p style="margin:0;font-size:15px;font-weight:800;letter-spacing:1px;color:#ff8b9c;">TEST EMAIL — NOT A REAL TICKET</p>
      <p style="margin:6px 0 0;font-size:12px;color:#e7b1ba;">{{ $testNote ?: 'Sample data. The QR codes below do not open the gate.' }}</p>
    </div>
  @endif

  <table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0" style="background:#15151e;border:1px solid #26263a;border-radius:10px;border-collapse:separate;margin:0 0 6px;">
    <tr><td style="{{ $dt }}" width="34%">Event</td><td style="{{ $dv }}font-weight:700;color:#fff;">{{ $event->name }}</td></tr>
    <tr><td style="{{ $dt }}">Date &amp; time</td><td style="{{ $dv }}">{{ $event->formatted_date_range }}@if($event->event_time) · {{ \Carbon\Carbon::parse($event->event_time)->format('g:i A') }}@endif</td></tr>
    @if($event->location)<tr><td style="padding:11px 0 11px 18px;font-size:11px;letter-spacing:.6px;text-transform:uppercase;color:#8a8aa3;vertical-align:top;">Venue</td><td style="padding:11px 18px 11px 12px;font-size:14px;color:#dddde8;text-align:right;vertical-align:top;">{{ $event->location }}</td></tr>@endif
  </table>

  @foreach($items as $i => $it)
    <div class="eticket" style="text-align:center;margin-top:14px;{{ $test ? 'border-color:#e8445a;' : '' }}">
      <p class="eticket-name" style="margin-bottom:2px;">{{ $it['holder'] ?: 'Ticket holder' }}</p>
      <p class="eticket-meta" style="margin-bottom:12px;">{{ $it['type'] }}@if(count($items) > 1) · Ticket {{ $i + 1 }} of {{ count($items) }}@endif</p>
      @if(! empty($it['png']))
        <div style="display:inline-block;background:#fff;border-radius:12px;padding:8px;">
          <img src="{{ $message->embedData($it['png'], 'qr-'.($i + 1).'.png', 'image/png') }}" alt="QR code for {{ $it['holder'] }}" width="200" height="200" style="display:block;width:200px;height:200px;margin:0 auto;">
        </div>
      @else
        <p class="ep-sm">Open your ticket to see its QR code.</p>
      @endif
      @if($test)<p style="margin:8px 0 0;font-size:12px;font-weight:800;letter-spacing:1px;color:#ff8b9c;">TEST — NOT VALID FOR ENTRY</p>@endif
    </div>
  @endforeach

  <div class="ecta-wrap" style="margin-top:26px;">
    <a class="ecta" href="{{ $viewUrl }}" style="text-transform:uppercase;letter-spacing:.6px;">View your tickets</a>
    <p class="ecta-sub">Your tickets are always available in your TKT House account.</p>
  </div>

  <div class="ealert gold">Each QR code works once. Please do not share it: the first scan at the gate admits the ticket.</div>
@endsection
