@extends('admin.master')
@section('title', 'Edit Ticket')

@section('content')
@php
  $statusOptions = [
    'not_checked_in' => 'Not Checked In',
    'checked_in'     => 'Checked In',
    'canceled'       => 'Canceled',
  ];
@endphp

<x-admin.page-header :title="'Edit Ticket #'.($ticket->ticket_number ?? 'N/A')" :subtitle="($ticket->eventLabel() ?: 'No event assigned').' — update holder info and status.'" :crumbs="['Dashboard' => route('admin.dashboard'), 'Tickets' => route('admin.tickets.index'), $ticket->ticket_number ?? 'Ticket' => route('admin.tickets.show', $ticket), 'Edit']">
    <a href="{{ route('admin.tickets.show', $ticket) }}" class="a-btn a-btn--outline"><i class="fa fa-eye"></i> View</a>
    <a href="{{ route('admin.tickets.download', $ticket) }}" class="a-btn a-btn--outline"><i class="fa fa-download"></i> PDF</a>
</x-admin.page-header>

<x-admin.flash />

<form method="POST" action="{{ route('admin.tickets.update', $ticket) }}" data-a-loading>
  @csrf
  @method('PUT')

  <div class="a-grid a-grid--main-side">
    <div class="a-tk-stack">
      <x-admin.card title="Holder Information">
        <div class="a-form-grid">
          <x-admin.field label="Holder Name" for="holder_name" name="holder_name">
            <input class="form-control" id="holder_name" type="text" name="holder_name" value="{{ old('holder_name', $ticket->holder_name) }}" placeholder="Full name…">
          </x-admin.field>
          <div class="a-form-grid a-form-grid--2">
            <x-admin.field label="Email" for="holder_email" name="holder_email">
              <input class="form-control" id="holder_email" type="email" name="holder_email" value="{{ old('holder_email', $ticket->holder_email) }}" placeholder="email@example.com">
            </x-admin.field>
            <x-admin.field label="Phone" for="holder_phone" name="holder_phone">
              <input class="form-control" id="holder_phone" type="tel" name="holder_phone" data-phone-intl value="{{ old('holder_phone', $ticket->holder_phone) }}" placeholder="1003770730">
            </x-admin.field>
          </div>
        </div>
      </x-admin.card>

      <x-admin.card title="Ticket Settings">
        <div class="a-form-grid a-form-grid--2">
          <x-admin.field label="Status" for="ticket_status" name="status">
            <select class="form-select" id="ticket_status" name="status">
              @foreach($statusOptions as $val => $label)
                <option value="{{ $val }}" {{ old('status', $ticket->status) === $val ? 'selected' : '' }}>{{ $label }}</option>
              @endforeach
            </select>
          </x-admin.field>
          <x-admin.field label="Ticket Number" for="ticket_number_ro" hint="Read-only">
            <input class="form-control a-tk-readonly" id="ticket_number_ro" type="text" value="{{ $ticket->ticket_number ?? 'N/A' }}" readonly>
          </x-admin.field>
        </div>

        <div class="a-form-actions">
          <a href="{{ route('admin.tickets.show', $ticket) }}" class="a-btn a-btn--ghost">Cancel</a>
          <button type="submit" class="a-btn a-btn--primary"><i class="fa fa-save"></i> Save Changes</button>
        </div>
      </x-admin.card>
    </div>

    <div class="a-tk-side">
      <x-admin.card title="QR Code">
        <div class="a-tk-qr">
          <img src="https://api.qrserver.com/v1/create-qr-code/?size=200x200&data={{ urlencode($ticket->qr_payload ?: $ticket->ticket_number) }}" alt="QR Code for {{ $ticket->ticket_number }}">
          <span class="a-tk-qr__num">{{ $ticket->ticket_number ?? 'N/A' }}</span>
          <span class="a-hint">Scan at event entrance</span>
        </div>
      </x-admin.card>

      <x-admin.card title="Ticket Info">
        <dl class="a-kv">
          <div><dt>Event</dt><dd>{{ $ticket->eventLabel() ?: '-' }}</dd></div>
          <div><dt>Type</dt><dd>{{ $ticket->ticketTypeLabel() ?: '-' }}</dd></div>
          <div><dt>Order #</dt><dd>@if($ticket->order)<a href="{{ route('admin.orders.show', $ticket->order) }}">{{ $ticket->order->order_number }}</a>@else - @endif</dd></div>
          <div><dt>Status</dt><dd><x-admin.status :status="$ticket->status" kind="ticket" /></dd></div>
          <div><dt>Issued At</dt><dd>{{ $ticket->issued_at?->format('d M Y, H:i') ?: '—' }}</dd></div>
          <div><dt>Checked In</dt><dd>{{ $ticket->checked_in_at?->format('d M Y, H:i') ?: '—' }}</dd></div>
        </dl>
      </x-admin.card>
    </div>
  </div>
</form>
@endsection
