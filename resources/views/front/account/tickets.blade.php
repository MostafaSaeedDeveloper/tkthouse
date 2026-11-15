@extends('front.account.layout')
@section('title', 'My tickets')
@section('heading', 'My tickets')

@php
    $tabs = ['upcoming' => 'Upcoming', 'past' => 'Past', 'used' => 'Used', 'canceled' => 'Canceled / Refunded'];
    $sectionTitles = ['upcoming' => 'Upcoming', 'past' => 'Past', 'used' => 'Used', 'canceled' => 'Canceled / Refunded'];
    $emptyTitles = ['upcoming' => 'No upcoming tickets', 'past' => 'No past tickets', 'used' => 'No used tickets', 'canceled' => 'No canceled or refunded tickets', 'all' => 'No tickets yet'];
    $linkFor = fn (string $w) => route('front.account.tickets', array_filter(['when' => $w, 'order' => $orderNumber ?: null]));
@endphp

@section('portal')
<section aria-labelledby="ticketsTitle" class="tkt-tl">
    @if($orderNumber !== '')
        <div class="tkt-tl__filter">
            <span>Showing tickets of order <b>{{ $orderNumber }}</b></span>
            <a class="tkt-link" href="{{ route('front.account.tickets') }}">Show all my tickets</a>
        </div>
    @endif

    <div class="tkt-portal__chips" role="group" aria-label="Filter tickets">
        @foreach($tabs as $key => $label)
            <a href="{{ $linkFor($key) }}" class="tkt-chip {{ $when === $key ? 'is-active' : '' }}" @if($when === $key) aria-current="true" @endif>{{ $label }} <span class="tkt-chip__n">{{ $counts[$key] }}</span></a>
        @endforeach
    </div>

    @if($sections->isEmpty())
        <x-tkt.empty-state icon="ticket" :title="$emptyTitles[$when]" :text="$total === 0 ? 'Tickets appear here once your payment is confirmed.' : 'Your other tickets are in the other tabs.'">
            @if($total > 0 && $when !== 'all')<a class="tkt-btn tkt-btn--outline" href="{{ $linkFor('all') }}">Show all tickets</a>@endif
            <a class="tkt-btn tkt-btn--primary" href="{{ route('front.events') }}">Browse events</a>
        </x-tkt.empty-state>
    @else
        @foreach($sections as $key => $groups)
            @if($when === 'all')<h2 class="tkt-tl__section">{{ $sectionTitles[$key] }}</h2>@endif
            @foreach($groups as $group)
                @php
                    $order = $group['order'];
                    $event = $group['tickets']->first()->orderItem?->event;
                    $image = $event?->cover_image_url ?: $event?->event_banner_url;
                @endphp
                <article class="tkt-tgroup" aria-label="Order {{ $order->order_number }}">
                    <header class="tkt-tgroup__head">
                        <div class="tkt-media tkt-tgroup__img {{ $image ? '' : 'is-empty' }}">@if($image)<img class="tkt-img" src="{{ $image }}" alt="" loading="lazy">@endif</div>
                        <div class="tkt-tgroup__title">
                            <h3>{{ $event?->name ?? 'Event' }}</h3>
                            <p>@if($event){{ $event->formatted_date_range }}@if($event->event_time) · {{ \Carbon\Carbon::parse($event->event_time)->format('g:i A') }}@endif @endif</p>
                        </div>
                        @if($group['tickets']->count() > 1)<span class="tkt-tgroup__count">{{ $group['tickets']->count() }} tickets</span>@endif
                    </header>
                    <ul class="tkt-tgroup__list">
                        @foreach($group['tickets'] as $ticket)
                            @php
                                [$label, $variant] = \App\Support\CustomerPortal::ticketBadge($ticket, $event);
                                $qr = \App\Support\CustomerPortal::qrState($ticket, $event);
                            @endphp
                            <li>
                                <a class="tkt-tcard" href="{{ route('front.tickets.show', $ticket) }}">
                                    <span class="tkt-tcard__ic {{ $qr['state'] === 'visible' ? 'is-ready' : '' }}" aria-hidden="true">
                                        @if($qr['state'] === 'visible')
                                            <svg viewBox="0 0 24 24" width="22" height="22" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="3" width="7" height="7" rx="1"/><rect x="14" y="3" width="7" height="7" rx="1"/><rect x="3" y="14" width="7" height="7" rx="1"/><path d="M14 14h3v3m4 0v4h-4m-3 0v-3"/></svg>
                                        @elseif($qr['state'] === 'locked')
                                            <svg viewBox="0 0 24 24" width="22" height="22" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="4" y="11" width="16" height="10" rx="2"/><path d="M8 11V8a4 4 0 0 1 8 0v3"/></svg>
                                        @else
                                            <svg viewBox="0 0 24 24" width="22" height="22" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="9"/><path d="m5.6 5.6 12.8 12.8"/></svg>
                                        @endif
                                    </span>
                                    <span class="tkt-tcard__body">
                                        <b class="tkt-tcard__name">{{ $ticket->holder_name ?: 'Attendee' }}</b>
                                        <span class="tkt-tcard__type">{{ $ticket->ticketTypeLabel() }}</span>
                                        @if($qr['state'] === 'locked')
                                            <small>QR unlocks {{ $qr['reveals_at'] ? $qr['reveals_at']->format('D j M · g:i A') : 'closer to the event' }}</small>
                                        @else
                                            <small>Order {{ $order->order_number }}</small>
                                        @endif
                                    </span>
                                    <span class="tkt-tcard__end"><x-tkt.badge :variant="$variant">{{ $label }}</x-tkt.badge></span>
                                </a>
                            </li>
                        @endforeach
                    </ul>
                </article>
            @endforeach
        @endforeach
    @endif
</section>
@endsection
