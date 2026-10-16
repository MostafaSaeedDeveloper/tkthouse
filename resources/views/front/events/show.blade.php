@extends('front.layout.app')

@section('title', $event->name)

@section('content')
@php
    $isPreviousEvent = $event->event_date->lt(now()->startOfDay());
    $isEventSoldOut = $event->status === 'sold_out';
    $isBookingClosed = $isPreviousEvent || $isEventSoldOut;
    $multiDay = $event->end_date && $event->end_date->ne($event->event_date);
    $time = $event->event_time ? \Carbon\Carbon::parse($event->event_time)->format('g:i A') : null;
    $banner = $event->event_banner_url ?: $event->cover_image_url;

    $tickets = $event->tickets->filter(fn ($t) => $t->status !== 'hidden')->values();
    // Available first, unavailable after (each group keeps the organiser's own order). Nothing is renamed or grouped by name.
    [$available, $unavailable] = $tickets->partition(fn ($t) => ! ($isBookingClosed || $t->status === 'sold_out'));
    $available = $available->values();
    $unavailable = $unavailable->values();
    $collapseAfter = 6;
    $descriptionHtml = \App\Support\SafeHtml::clean($event->description);
    $rules = collect(preg_split('/\r\n|\r|\n/', (string) $event->house_rules))->map(fn ($rule) => trim($rule))->filter()->values();
    if ($rules->isEmpty()) {
        $rules = collect([
            'Follow venue instructions and security guidelines during the event.',
            'Please arrive early to complete the entry process smoothly.',
        ]);
    }
    $mapEmbedHtml = \App\Support\SafeMapEmbed::render($event->map_embed);
    $mapLink = filter_var((string) $event->map_url, FILTER_VALIDATE_URL) && preg_match('#^https?://#i', (string) $event->map_url) ? $event->map_url : null;
    $hasVenue = $mapEmbedHtml !== '' || $event->venue_map_url || $mapLink;
    $fees = $event->fees->map(fn ($fee) => [
        'name' => (string) $fee->name,
        'type' => (string) $fee->fee_type,
        'value' => (float) ($fee->value ?? 0),
    ])->values();

    $tabs = collect([['id' => 'about', 'label' => 'About']]);
    if ($event->lineups->isNotEmpty()) { $tabs->push(['id' => 'lineup', 'label' => 'Lineup']); }
    if ($hasVenue) { $tabs->push(['id' => 'venue', 'label' => 'Venue']); }
    if ($event->images->isNotEmpty()) { $tabs->push(['id' => 'gallery', 'label' => 'Gallery']); }
    $tabs->push(['id' => 'rules', 'label' => 'House rules']);
@endphp

{{-- Event header --}}
<section class="tkt-event-hero">
    @if($banner)
        <div class="tkt-media tkt-event-hero__media">
            <img class="tkt-img" src="{{ $banner }}" alt="" fetchpriority="high">
        </div>
    @endif
    <div class="tkt-container">
        <div class="tkt-event-hero__inner">
            <a class="tkt-back" href="{{ route('front.events') }}">
                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M19 12H5M11 6l-6 6 6 6"/></svg>
                Back to events
            </a>
            <div class="tkt-event-hero__row">
                <div class="tkt-datebox" aria-hidden="true">
                    <span>{{ $event->event_date->format('M') }}</span>
                    <b>{{ $event->event_date->format('d') }}</b>
                </div>
                <div style="min-width:0">
                    <div class="tkt-event-hero__badges">
                        @if($isPreviousEvent)
                            <x-tkt.badge variant="glass">Event ended</x-tkt.badge>
                        @elseif($isEventSoldOut)
                            <x-tkt.badge variant="danger">Sold out</x-tkt.badge>
                        @else
                            <x-tkt.badge variant="primary" :dot="true">Tickets on sale</x-tkt.badge>
                        @endif
                    </div>
                    <h1 class="tkt-event-hero__title">{{ $event->name }}</h1>
                </div>
            </div>
            <ul class="tkt-event-hero__meta">
                <li>
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><rect x="3" y="4" width="18" height="18" rx="2"/><path d="M16 2v4M8 2v4M3 10h18"/></svg>
                    <span>{{ $event->formatted_date_range }}</span>
                </li>
                @if($time)
                    <li>
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><circle cx="12" cy="12" r="10"/><path d="M12 6v6l4 2"/></svg>
                        <span>{{ $time }}</span>
                    </li>
                @endif
                @if($event->location)
                    <li>
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M21 10c0 7-9 13-9 13S3 17 3 10a9 9 0 0 1 18 0z"/><circle cx="12" cy="10" r="3"/></svg>
                        <span>{{ $event->location }}</span>
                    </li>
                @endif
            </ul>
        </div>
    </div>
</section>

<div class="tkt-container">
    <div class="tkt-event-layout">

        {{-- Content --}}
        <div class="tkt-event-main">
            {{-- Ticket selection: every ticket is independent (name, price, rules come from the database) --}}
            <section class="tkt-tickets-section" id="tktBooking" aria-labelledby="ticketsTitle"
                     data-checkout-base="{{ route('front.checkout') }}"
                     data-event-id="{{ $event->id }}"
                     data-booking-closed="{{ $isBookingClosed ? 1 : 0 }}"
                     data-collapse-after="{{ $collapseAfter }}"
                     data-fees='@json($fees)'>
                <div class="tkt-tickets-section__head">
                    <h2 class="tkt-tickets-section__title" id="ticketsTitle">Select tickets</h2>
                    @if(! $isPreviousEvent && $available->isNotEmpty())
                        <span class="tkt-tickets-section__count">{{ $available->count() }} {{ \Illuminate\Support\Str::plural('option', $available->count()) }} available</span>
                    @endif
                </div>

                @if($isPreviousEvent)
                    <div class="tkt-closed">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><circle cx="12" cy="12" r="10"/><path d="M12 8v4M12 16h.01"/></svg>
                        <div>
                            <strong>This event has ended.</strong>
                            <p class="tkt-text-muted" style="margin-top:2px">Tickets are no longer available. Browse our upcoming events instead.</p>
                        </div>
                    </div>
                    <a class="tkt-btn tkt-btn--primary" style="margin-top:14px" href="{{ route('front.events', ['when' => 'upcoming']) }}">See upcoming events</a>
                @else
                    @if($isEventSoldOut)
                        <div class="tkt-alert tkt-alert--danger" style="margin-bottom:12px">This event is sold out.</div>
                    @endif

                    @if($tickets->isEmpty())
                        <x-tkt.empty-state icon="ticket" title="Tickets not available yet" text="Please check again later." style="padding:28px 16px" />
                    @endif

                    @if($available->isNotEmpty())
                        <div class="tkt-tickets {{ $available->count() > $collapseAfter ? 'is-collapsed' : '' }}" id="ticketList" data-ticket-list>
                            @foreach($available as $i => $ticket)
                                @include('front.events._ticket', ['ticket' => $ticket, 'unavailable' => false, 'extra' => $i >= $collapseAfter])
                            @endforeach
                        </div>
                        @if($available->count() > $collapseAfter)
                            <button type="button" class="tkt-btn tkt-btn--outline tkt-btn--block tkt-more" data-show-more="#ticketList" aria-expanded="false"
                                    data-more-label="Show more tickets" data-less-label="Show fewer tickets">
                                <span data-more-text>Show more tickets ({{ $available->count() - $collapseAfter }})</span>
                            </button>
                        @endif
                    @endif

                    @if($unavailable->isNotEmpty())
                        <div class="tkt-tickets-off">
                            <h3 class="tkt-tickets-off__title">{{ $isBookingClosed ? 'Tickets' : 'Sold out' }}</h3>
                            <div class="tkt-tickets tkt-tickets--off {{ $unavailable->count() > 3 ? 'is-collapsed' : '' }}" id="offList">
                                @foreach($unavailable as $i => $ticket)
                                    @include('front.events._ticket', ['ticket' => $ticket, 'unavailable' => true, 'extra' => $i >= 3])
                                @endforeach
                            </div>
                            @if($unavailable->count() > 3)
                                <button type="button" class="tkt-btn tkt-btn--ghost tkt-more tkt-more--off" data-show-more="#offList" aria-expanded="false"
                                        data-more-label="Show all sold-out tickets" data-less-label="Hide sold-out tickets">
                                    <span data-more-text>Show all sold-out tickets ({{ $unavailable->count() - 3 }} more)</span>
                                </button>
                            @endif
                        </div>
                    @endif
                @endif
            </section>

            <div class="tkt-tabs" role="tablist" aria-label="Event information" data-tkt-tabs>
                @foreach($tabs as $i => $tab)
                    <button type="button" class="tkt-tab" role="tab" id="tab-{{ $tab['id'] }}" aria-controls="panel-{{ $tab['id'] }}" aria-selected="{{ $i === 0 ? 'true' : 'false' }}" tabindex="{{ $i === 0 ? 0 : -1 }}">{{ $tab['label'] }}</button>
                @endforeach
            </div>

            <div class="tkt-tabpanel is-active" role="tabpanel" id="panel-about" aria-labelledby="tab-about">
                @if($descriptionHtml !== '')
                    <div class="tkt-prose">{!! $descriptionHtml !!}</div>
                @endif

                <dl class="tkt-detail-list" style="{{ $descriptionHtml === '' ? 'margin-top:0' : '' }}">
                    <div>
                        <dt>Date</dt>
                        <dd>{{ $event->formatted_date_range }}</dd>
                    </div>
                    @if($time)
                        <div>
                            <dt>Time</dt>
                            <dd>{{ $time }}</dd>
                        </div>
                    @endif
                    @if($event->location)
                        <div>
                            <dt>Location</dt>
                            <dd>
                                @if($mapLink)
                                    <a href="{{ $mapLink }}" target="_blank" rel="noopener noreferrer">{{ $event->location }}</a>
                                @else
                                    {{ $event->location }}
                                @endif
                            </dd>
                        </div>
                    @endif
                </dl>

                @if($event->id === 27)
                    <div class="tkt-organizers">
                        <div class="tkt-eyebrow">Organizers</div>
                        <div class="tkt-organizer">
                            <img src="https://i.ibb.co/MDYChVSt/b751fb37-bd47-4bb9-bb18-693cd2bf118f.jpg" alt="Fracture Company logo" loading="lazy">
                            <div>
                                <b>FRACTURE COMPANY</b>
                                <a href="https://www.instagram.com/fracture.sound/" target="_blank" rel="noopener noreferrer"><i class="fa fa-instagram"></i> fracture.sound</a>
                            </div>
                        </div>
                        <div class="tkt-organizer">
                            <img src="https://i.ibb.co/Kj49Q6cL/0da52100-622e-498d-a417-f0aebebc4a5f.jpg" alt="Vavuraa logo" loading="lazy">
                            <div>
                                <b>VAVURAA</b>
                                <a href="https://www.instagram.com/vavuraa" target="_blank" rel="noopener noreferrer"><i class="fa fa-instagram"></i> vavuraa</a>
                            </div>
                        </div>
                    </div>
                @endif
            </div>

            @if($event->lineups->isNotEmpty())
                <div class="tkt-tabpanel" role="tabpanel" id="panel-lineup" aria-labelledby="tab-lineup">
                    <ul class="tkt-lineup">
                        @foreach($event->lineups as $artist)
                            <li class="tkt-lineup__item">
                                <div class="tkt-lineup__avatar">
                                    @if($artist->image_url)
                                        <img src="{{ $artist->image_url }}" alt="{{ $artist->artist_name }}" loading="lazy">
                                    @else
                                        <i class="fa fa-music" aria-hidden="true"></i>
                                    @endif
                                </div>
                                <div class="tkt-lineup__info">
                                    <div class="tkt-lineup__name">{{ $artist->artist_name }}</div>
                                    @if($artist->performance_date || $artist->performance_time)
                                        <div class="tkt-lineup__when">{{ $artist->performance_date?->format('d M Y') }}@if($artist->performance_date && $artist->performance_time) · @endif{{ $artist->performance_time }}</div>
                                    @endif
                                </div>
                                @if($artist->instagram)
                                    <a class="tkt-lineup__ig" href="https://www.instagram.com/{{ ltrim($artist->instagram, '@') }}" target="_blank" rel="noopener noreferrer" aria-label="{{ $artist->artist_name }} on Instagram">
                                        <i class="fa fa-instagram" aria-hidden="true"></i><span>{{ ltrim($artist->instagram, '@') }}</span>
                                    </a>
                                @endif
                            </li>
                        @endforeach
                    </ul>
                </div>
            @endif

            @if($hasVenue)
                <div class="tkt-tabpanel" role="tabpanel" id="panel-venue" aria-labelledby="tab-venue">
                    @if($mapEmbedHtml !== '')
                        <div class="tkt-venue-block tkt-map">{!! $mapEmbedHtml !!}</div>
                    @endif
                    @if($mapLink)
                        <p class="tkt-venue-block"><a class="tkt-btn tkt-btn--outline tkt-btn--sm" href="{{ $mapLink }}" target="_blank" rel="noopener noreferrer">Open in Maps</a></p>
                    @endif
                    @if($event->venue_map_url)
                        <div class="tkt-venue-block">
                            <h3 class="tkt-h4">Venue map</h3>
                            <a href="{{ $event->venue_map_url }}" data-tkt-lightbox data-alt="{{ $event->name }} venue map">
                                <img class="tkt-venue-img" src="{{ $event->venue_map_url }}" alt="{{ $event->name }} venue map" loading="lazy">
                            </a>
                        </div>
                    @endif
                </div>
            @endif

            @if($event->images->isNotEmpty())
                <div class="tkt-tabpanel" role="tabpanel" id="panel-gallery" aria-labelledby="tab-gallery">
                    <div class="tkt-gallery">
                        @foreach($event->images as $image)
                            @if($image->path_url)
                                <a href="{{ $image->path_url }}" data-tkt-lightbox data-alt="{{ $event->name }} photo {{ $loop->iteration }}">
                                    <img src="{{ $image->path_url }}" alt="{{ $event->name }} photo {{ $loop->iteration }}" loading="lazy">
                                </a>
                            @endif
                        @endforeach
                    </div>
                </div>
            @endif

            <div class="tkt-tabpanel" role="tabpanel" id="panel-rules" aria-labelledby="tab-rules">
                <ol class="tkt-rules">
                    @foreach($rules as $rule)
                        <li>{{ $rule }}</li>
                    @endforeach
                </ol>
            </div>
        </div>

        {{-- Order summary: separate from the ticket list; a sticky card on desktop, a bottom bar on mobile --}}
        @unless($isPreviousEvent)
        <aside class="tkt-event-aside" aria-label="Order summary">
            <div class="tkt-card tkt-summary-card">
                <h2 class="tkt-summary-card__title">Your order</h2>
                <div class="tkt-summary" id="orderSummary">
                    <div class="tkt-summary__detail">
                        <p class="tkt-summary__empty">No tickets selected yet</p>
                        <div class="tkt-summary__lines" id="summaryLines"></div>
                    </div>
                    <div class="tkt-summary__total">
                        <span>Total<button type="button" class="tkt-summary__toggle" id="summaryToggle" aria-expanded="false">Details</button></span>
                        <b id="summaryTotal">0.00 EGP</b>
                    </div>
                    <a href="{{ route('front.checkout') }}" class="tkt-btn tkt-btn--primary tkt-btn--lg tkt-btn--block tkt-checkout-btn is-disabled" id="checkoutBtn" data-express-checkout aria-disabled="true" tabindex="-1">
                        {{ $isBookingClosed ? 'Booking closed' : 'Continue' }}
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M5 12h14M13 6l6 6-6 6"/></svg>
                    </a>
                    <p class="tkt-summary__note">{{ $isBookingClosed ? 'This event is sold out, so checkout is disabled.' : 'No account needed · Final amount shown at checkout' }}</p>
                </div>
            </div>
        </aside>
        @endunless
    </div>
</div>
@endsection

@push('scripts')
    <script src="{{ asset('js/tkt-booking.js') }}?v={{ filemtime(public_path('js/tkt-booking.js')) }}"></script>
@endpush
