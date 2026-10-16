@extends('admin.master')
@section('title', 'Ticket '.($ticket->ticket_number ?? ''))

@section('content')
@php
  $statusLabels = [
    'not_checked_in' => 'Not Checked In',
    'checked_in'     => 'Checked In',
    'canceled'       => 'Canceled',
  ];
  $statusLabel = $statusLabels[$ticket->status] ?? str($ticket->status)->headline();
  $initials = collect(explode(' ', (string)($ticket->holder_name ?: 'NA')))->filter()->map(fn($p)=>mb_substr($p,0,1))->take(2)->implode('');
@endphp

<x-admin.page-header :title="'Ticket #'.($ticket->ticket_number ?? 'N/A')" :subtitle="$ticket->eventLabel() ?: 'No event assigned'" :crumbs="['Dashboard' => route('admin.dashboard'), 'Tickets' => route('admin.tickets.index'), $ticket->ticket_number ?? 'Ticket']">
    @can('tickets.update')
        <a href="{{ route('admin.tickets.edit', $ticket) }}" class="a-btn a-btn--primary"><i class="fa fa-pen"></i> Edit</a>
    @endcan
    <a href="{{ route('admin.tickets.download', $ticket) }}" class="a-btn a-btn--outline"><i class="fa fa-download"></i> PDF</a>
    <x-admin.dropdown label="" aria-label="More actions">
        <a class="a-menu__item" href="{{ route('admin.tickets.index') }}"><i class="fa fa-arrow-left"></i> Back to tickets</a>
        @can('tickets.delete')
            <div class="a-menu__sep"></div>
            <form method="POST" action="{{ route('admin.tickets.destroy', $ticket) }}" data-a-confirm="Delete ticket {{ $ticket->ticket_number }}? This cannot be undone." data-a-confirm-title="Delete ticket" data-a-confirm-ok="Delete">
                @csrf @method('DELETE')
                <button class="a-menu__item a-menu__item--danger" type="submit"><i class="fa fa-trash"></i> Delete</button>
            </form>
        @endcan
    </x-admin.dropdown>
</x-admin.page-header>

<x-admin.flash />

<div class="a-grid a-grid--main-side">
  <div class="a-stack">

    <x-admin.card title="Ticket Info">
      <x-slot:actions><x-admin.status :status="$ticket->status" kind="ticket" /></x-slot:actions>
      <div class="a-tk-holder">
        <div class="a-tk-avatar" aria-hidden="true">{{ $initials ?: 'NA' }}</div>
        <div class="a-tk-holder__text">
          <div class="a-tk-holder__name">{{ $ticket->holder_name ?: 'Unknown Holder' }}</div>
          <div class="a-tk-holder__sub">{{ $ticket->holder_email ?: '-' }}</div>
        </div>
      </div>
      <dl class="a-kv">
        <div><dt>Ticket #</dt><dd>{{ $ticket->ticket_number ?? '-' }}</dd></div>
        <div><dt>Event</dt><dd>{{ $ticket->eventLabel() ?: '-' }}</dd></div>
        <div><dt>Ticket Type</dt><dd>{{ $ticket->ticketTypeLabel() ?: '-' }}</dd></div>
        <div><dt>Order #</dt><dd>@if($ticket->order)<a href="{{ route('admin.orders.show', $ticket->order) }}">{{ $ticket->order->order_number }}</a>@else - @endif</dd></div>
        <div><dt>Phone</dt><dd>{{ $ticket->holder_phone ?: '-' }}</dd></div>
      </dl>
    </x-admin.card>

    <x-admin.card title="Timeline">
      <dl class="a-kv">
        <div><dt>Created At</dt><dd>{{ $ticket->created_at?->format('d M Y, H:i') ?: '-' }}</dd></div>
        <div><dt>Issued At</dt><dd>{{ $ticket->issued_at?->format('d M Y, H:i') ?: '-' }}</dd></div>
        <div><dt>Checked In At</dt><dd>{{ $ticket->checked_in_at?->format('d M Y, H:i') ?: '—' }}</dd></div>
        <div><dt>Canceled At</dt><dd>{{ $ticket->canceled_at?->format('d M Y, H:i') ?: '—' }}</dd></div>
        <div><dt>Updated At</dt><dd>{{ $ticket->updated_at?->format('d M Y, H:i') ?: '-' }}</dd></div>
      </dl>
    </x-admin.card>

    <x-admin.card title="Log History">
      @forelse($scanLogs as $log)
        @php
          $fromStatus = $log->previous_status ? str($log->previous_status)->replace('_',' ')->title() : '-';
          $toStatus = $log->new_status ? str($log->new_status)->replace('_',' ')->title() : '-';
          $scannerDisplay = $log->scanner_name ?: ($log->scannerUser?->name ?? $log->scannerUser?->username ?? 'Unknown');
        @endphp
        @if($loop->first)<ul class="a-tk-log">@endif
          <li>
            <span class="a-tk-log__when">{{ $log->scanned_at?->format('d M Y, H:i') ?: '-' }}</span>
            <span>{{ $fromStatus }} → {{ $toStatus }}</span>
            <span class="a-table__sub">by {{ $scannerDisplay }}</span>
          </li>
        @if($loop->last)</ul>@endif
      @empty
        <x-admin.empty icon="fa-clock-rotate-left" title="No status history yet" />
      @endforelse
    </x-admin.card>

    @can('tickets.update')
    <x-admin.card title="Send Ticket via Email">
      <form method="POST" action="{{ route('admin.tickets.send-email', $ticket) }}" data-a-loading>
        @csrf
        <x-admin.field label="Email Address" for="send_email" name="email">
          <input type="email" id="send_email" name="email" class="form-control" value="{{ old('email', $ticket->holder_email) }}" placeholder="recipient@example.com" required>
        </x-admin.field>
        <div class="a-tk-send"><button class="a-btn a-btn--primary" type="submit"><i class="fa fa-envelope"></i> Send Email</button></div>
      </form>
    </x-admin.card>

    @if($whatsappEnabled)
    <x-admin.card title="Send Ticket via WhatsApp">
      <form method="POST" action="{{ route('admin.tickets.send-whatsapp', $ticket) }}" data-a-loading>
        @csrf
        <x-admin.field label="WhatsApp Number" for="send_phone" name="phone">
          <input type="tel" id="send_phone" name="phone" data-phone-intl class="form-control" value="{{ old('phone', $ticket->holder_phone) }}" placeholder="1003770730" required>
        </x-admin.field>
        <div class="a-tk-send"><button class="a-btn a-btn--success" type="submit"><i class="fa-brands fa-whatsapp"></i> Send WhatsApp</button></div>
      </form>
    </x-admin.card>
    @endif
    @endcan

  </div>

  <div class="a-stack">
    <x-admin.card title="QR Code">
      <div class="a-tk-qr">
        <img src="https://api.qrserver.com/v1/create-qr-code/?size=220x220&data={{ urlencode($ticket->qr_payload ?: $ticket->ticket_number) }}" alt="QR Code for {{ $ticket->ticket_number }}">
        <span class="a-tk-qr__num">{{ $ticket->ticket_number ?? 'N/A' }}</span>
        <span class="a-hint">Scan at event entrance</span>
      </div>
    </x-admin.card>

    <x-admin.card title="Status">
      <div class="a-tk-qr">
        <x-admin.status :status="$ticket->status" kind="ticket" />
        @if($ticket->checked_in_at)
          <span class="a-hint">Checked in {{ $ticket->checked_in_at->diffForHumans() }}</span>
        @endif
      </div>
    </x-admin.card>
  </div>
</div>
@endsection
