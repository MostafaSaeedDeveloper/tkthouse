@extends('front.account.layout')
@section('title', 'My tickets')
@section('heading', 'My tickets')

@section('portal')
@php
    $tabs = ['upcoming' => 'Upcoming', 'past' => 'Past', 'all' => 'All'];
@endphp
<section aria-labelledby="ticketsTitle">
    <div class="tkt-portal__chips" role="group" aria-label="Filter tickets">
        @foreach($tabs as $key => $label)
            <a href="{{ route('front.account.tickets', ['when' => $key]) }}" class="tkt-chip {{ $when === $key ? 'is-active' : '' }}" @if($when === $key) aria-current="true" @endif>{{ $label }}</a>
        @endforeach
    </div>

    @if($groups->isEmpty())
        <x-tkt.empty-state icon="ticket" :title="$when === 'past' ? 'No past tickets' : ($when === 'upcoming' ? 'No upcoming tickets' : 'No tickets yet')" text="Tickets appear here once your payment is confirmed.">
            @if($when !== 'all')<a class="tkt-btn tkt-btn--outline" href="{{ route('front.account.tickets', ['when' => 'all']) }}">Show all tickets</a>@endif
            <a class="tkt-btn tkt-btn--primary" href="{{ route('front.events') }}">Browse events</a>
        </x-tkt.empty-state>
    @else
        @foreach($groups as $group)
            @php
                $order = $group['order'];
                $first = $group['tickets']->first();
                $event = $first->orderItem?->event;
                $image = $event?->cover_image_url ?: $event?->event_banner_url;
            @endphp
            <article class="tkt-tgroup" aria-label="Order {{ $order->order_number }}">
                <header class="tkt-tgroup__head">
                    <div class="tkt-media tkt-tgroup__img {{ $image ? '' : 'is-empty' }}">@if($image)<img class="tkt-img" src="{{ $image }}" alt="" loading="lazy">@endif</div>
                    <div class="tkt-tgroup__title">
                        <h2>{{ $event?->name ?? 'Event' }}</h2>
                        <p>@if($event){{ $event->formatted_date_range }}@if($event->location) · {{ $event->location }}@endif @endif</p>
                    </div>
                    <a class="tkt-link" href="{{ route('front.account.orders.show', $order->order_number) }}">Order {{ $order->order_number }}</a>
                </header>
                <ul class="tkt-tgroup__list">
                    @foreach($group['tickets'] as $ticket)
                        @php
                            [$label, $variant] = \App\Support\CustomerPortal::ticketBadge($ticket, $event);
                            $qr = \App\Support\CustomerPortal::qrVisible($ticket, $event);
                        @endphp
                        <li class="tkt-tcard">
                            @if($qr)
                                <a class="tkt-tcard__qr" href="{{ route('front.tickets.show', $ticket) }}" aria-label="Open ticket {{ $ticket->ticket_number }}">
                                    <img src="{{ $ticket->qrUrl() }}" alt="QR code for ticket {{ $ticket->ticket_number }}" width="96" height="96" loading="lazy">
                                </a>
                            @else
                                <span class="tkt-tcard__qr is-off" aria-hidden="true">QR</span>
                            @endif
                            <div class="tkt-tcard__body">
                                <b class="tkt-tcard__name">{{ $ticket->holder_name ?: 'Attendee' }}</b>
                                <span class="tkt-tcard__type">{{ $ticket->ticket_name }}</span>
                                <small>#{{ $ticket->ticket_number }}</small>
                                <x-tkt.badge :variant="$variant">{{ $label }}</x-tkt.badge>
                            </div>
                            <div class="tkt-tcard__actions">
                                <a class="tkt-btn tkt-btn--primary tkt-btn--sm" href="{{ route('front.tickets.show', $ticket) }}">View ticket</a>
                                <a class="tkt-btn tkt-btn--outline tkt-btn--sm" href="{{ route('front.tickets.download', $ticket) }}">Download PDF</a>
                            </div>
                        </li>
                    @endforeach
                </ul>
            </article>
        @endforeach
    @endif
</section>
@endsection
