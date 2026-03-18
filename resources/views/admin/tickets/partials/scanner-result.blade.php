@isset($ticket)
  @php
    $initials = collect(explode(' ', (string)($ticket->holder_name ?: 'NA')))
      ->filter()->map(fn($p) => mb_substr($p,0,1))->take(2)->implode('');
    $statusLabel = match($ticket->status) {
      'checked_in'     => 'Checked In',
      'not_checked_in' => 'Not Checked In',
      'canceled'       => 'Canceled',
      default          => str($ticket->status)->replace('_',' ')->title(),
    };
  @endphp

  <div class="sc-result">
    <div class="sc-result-header">
      <div class="sc-result-title">Ticket Found</div>
      <span class="sc-status {{ $ticket->status }}">
        @if($ticket->status === 'checked_in')
          <i class="fa fa-circle-check"></i>
        @elseif($ticket->status === 'canceled')
          <i class="fa fa-circle-xmark"></i>
        @else
          <i class="fa fa-clock"></i>
        @endif
        {{ $statusLabel }}
      </span>
    </div>

    <div class="sc-holder">
      <div class="sc-avatar">{{ $initials ?: 'NA' }}</div>
      <div>
        <div class="sc-holder-name">{{ $ticket->holder_name ?: 'Unknown' }}</div>
        <div class="sc-holder-email">{{ $ticket->holder_email ?: '-' }}</div>
        @if($ticket->holder_phone)
          <div class="sc-holder-phone"><i class="fa fa-phone" style="font-size:10px;margin-right:4px;"></i>{{ $ticket->holder_phone }}</div>
        @endif
      </div>
    </div>

    <div class="sc-info">
      <div class="sc-info-row">
        <span class="sc-info-label">Ticket #</span>
        <span class="sc-info-val" style="font-family:monospace;font-size:12px;">{{ $ticket->ticket_number }}</span>
      </div>
      <div class="sc-info-row">
        <span class="sc-info-label">Event</span>
        <span class="sc-info-val">{{ $ticket->eventLabel() ?: '-' }}</span>
      </div>
      <div class="sc-info-row">
        <span class="sc-info-label">Ticket Type</span>
        <span class="sc-info-val">{{ $ticket->ticketTypeLabel() ?: '-' }}</span>
      </div>
      <div class="sc-info-row">
        <span class="sc-info-label">Order #</span>
        <span class="sc-info-val">{{ $ticket->order?->order_number ?? '-' }}</span>
      </div>
      @if($ticket->checked_in_at)
        <div class="sc-info-row">
          <span class="sc-info-label">Checked In At</span>
          <span class="sc-info-val" style="color:var(--green);">{{ $ticket->checked_in_at->format('d M, H:i') }}</span>
        </div>
      @endif
    </div>

    <form method="POST" action="{{ route('admin.tickets.scanner.status', $ticket) }}">
      @csrf
      <div class="sc-actions">
        @if($ticket->status !== 'checked_in')
          <button name="status" value="checked_in" class="sc-act-btn sc-act-checkin" type="submit"><i class="fa fa-circle-check"></i> Check In</button>
        @endif
        @if($ticket->status !== 'canceled')
          <button name="status" value="canceled" class="sc-act-btn sc-act-cancel" type="submit"><i class="fa fa-ban"></i> Cancel</button>
        @endif
      </div>
    </form>

    <a href="{{ route('admin.tickets.scanner') }}" class="sc-act-btn sc-act-view sc-scan-another" data-scan-another="true">
      <i class="fa fa-qrcode"></i> Scan Another
    </a>
  </div>
@else
  <div class="sc-idle">
    <i class="fa fa-qrcode"></i>
    Scan a QR code or enter a ticket number above
  </div>
@endisset
