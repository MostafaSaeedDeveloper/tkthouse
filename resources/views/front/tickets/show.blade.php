@extends('front.account.layout')
@section('title', 'Ticket '.$ticket->ticket_number)
@section('heading', 'Your ticket')

@php
    [$label, $variant] = \App\Support\CustomerPortal::ticketBadge($ticket, $event);
    $qr = \App\Support\CustomerPortal::qrVisible($ticket, $event);
    $image = $event?->cover_image_url ?: $event?->event_banner_url;
@endphp

@section('portal')
<a class="tkt-portal__back" href="{{ route('front.account.tickets') }}">
    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M19 12H5M11 6l-6 6 6 6"/></svg> My tickets
</a>

<article class="tkt-gate" aria-label="Ticket {{ $ticket->ticket_number }}">
    <div class="tkt-gate__top">
        <x-tkt.badge :variant="$variant">{{ $label }}</x-tkt.badge>
        <h2 class="tkt-gate__event">{{ $event?->name ?? $ticket->ticket_name }}</h2>
        @if($event)<p class="tkt-gate__when">{{ $event->formatted_date_range }}@if($event->event_time) · {{ \Carbon\Carbon::parse($event->event_time)->format('g:i A') }}@endif</p>@endif
        @if($event?->location)<p class="tkt-gate__where">{{ $event->location }}</p>@endif
    </div>

    <div class="tkt-gate__qr">
        @if($qr)
            <img src="{{ $ticket->qrUrl() }}" alt="QR code for ticket {{ $ticket->ticket_number }}. Show it at the entrance.">
            <p>Show this code at the entrance</p>
        @else
            <div class="tkt-gate__qr-off">
                <b>{{ $label }}</b>
                <p>@if($label === 'Checked in')This ticket has already been scanned at the entrance.@elseif($label === 'Canceled')This ticket was canceled and can no longer be used.@else This event has ended.@endif</p>
            </div>
        @endif
    </div>

    <dl class="tkt-gate__meta">
        <div><dt>Attendee</dt><dd>{{ $ticket->holder_name ?: '—' }}</dd></div>
        <div><dt>Ticket type</dt><dd>{{ $ticket->ticket_name }}</dd></div>
        <div><dt>Ticket number</dt><dd>#{{ $ticket->ticket_number }}</dd></div>
        <div><dt>Order</dt><dd><a class="tkt-link" href="{{ route('front.account.orders.show', $ticket->order->order_number) }}">{{ $ticket->order->order_number }}</a></dd></div>
    </dl>

    <div class="tkt-gate__actions">
        <a class="tkt-btn tkt-btn--primary tkt-btn--lg tkt-btn--block" href="{{ route('front.tickets.download', $ticket) }}">Download PDF</a>
    </div>
</article>
@endsection
