@extends('emails.layouts.base', [
  'title' => 'Your Ticket Is Ready — TKT House',
  'heroIcon' => '🎫',
  'heroTitle' => 'Your Ticket Is Ready',
  'heroText' => 'Your ticket is attached as a PDF and can also be viewed from your secure ticket page.',
  'footerText' => 'This email was sent by TKT House ticketing service.',
])

@section('content')
  <p class="ep" style="margin-bottom:16px;">Hi <strong>{{ $ticket->holder_name ?: 'Guest' }}</strong>,</p>
  <p class="ep" style="margin-bottom:20px;">
    Your ticket has been issued successfully. We attached the PDF ticket to this email for easy download and check-in.
  </p>

  <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="background:#15151e;border:1px solid rgba(255,255,255,0.07);border-radius:10px;overflow:hidden;margin:22px 0;">
    <tr>
      <td style="padding:12px 16px;border-bottom:1px solid rgba(255,255,255,0.05);font-size:12px;color:#6f6f88;text-transform:uppercase;letter-spacing:0.6px;">Ticket Number</td>
      <td style="padding:12px 16px;border-bottom:1px solid rgba(255,255,255,0.05);font-size:14px;color:#f5b800;font-weight:700;text-align:right;">{{ $ticket->ticket_number }}</td>
    </tr>
    <tr>
      <td style="padding:12px 16px;border-bottom:1px solid rgba(255,255,255,0.05);font-size:12px;color:#6f6f88;text-transform:uppercase;letter-spacing:0.6px;">Ticket Type</td>
      <td style="padding:12px 16px;border-bottom:1px solid rgba(255,255,255,0.05);font-size:14px;color:#dddde8;text-align:right;">{{ $ticket->ticketTypeLabel() }}</td>
    </tr>
    <tr>
      <td style="padding:12px 16px;border-bottom:1px solid rgba(255,255,255,0.05);font-size:12px;color:#6f6f88;text-transform:uppercase;letter-spacing:0.6px;">Event</td>
      <td style="padding:12px 16px;border-bottom:1px solid rgba(255,255,255,0.05);font-size:14px;color:#dddde8;text-align:right;">{{ $ticket->eventLabel() }}</td>
    </tr>
    <tr>
      <td style="padding:12px 16px;border-bottom:1px solid rgba(255,255,255,0.05);font-size:12px;color:#6f6f88;text-transform:uppercase;letter-spacing:0.6px;">Order</td>
      <td style="padding:12px 16px;border-bottom:1px solid rgba(255,255,255,0.05);font-size:14px;color:#dddde8;text-align:right;">#{{ $ticket->order?->order_number ?: '—' }}</td>
    </tr>
    <tr>
      <td style="padding:12px 16px;font-size:12px;color:#6f6f88;text-transform:uppercase;letter-spacing:0.6px;">Sent To</td>
      <td style="padding:12px 16px;font-size:14px;color:#dddde8;text-align:right;">{{ $recipientEmail }}</td>
    </tr>
  </table>

  <div class="ecta-wrap" style="margin-top:26px;">
    <a class="ecta" href="{{ $showUrl }}">View Ticket</a>
    <p class="ecta-sub">If the button does not work, use the link below.</p>
  </div>

  <div class="eurl" style="margin-top:14px;"><a href="{{ $showUrl }}">{{ $showUrl }}</a></div>

  <div class="ealert blue" style="margin-top:18px;">
    The PDF attachment includes your ticket QR and details. Please keep it available for entry.
  </div>
@endsection
