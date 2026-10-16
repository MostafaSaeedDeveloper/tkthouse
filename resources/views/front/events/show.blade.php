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
    // Available first; sold-out (or any ticket of a closed event) go into the collapsed list. Each group keeps the server order.
    [$available, $unavailable] = $tickets->partition(fn ($t) => ! ($isBookingClosed || $t->status === 'sold_out'));
    $available = $available->values();
    $unavailable = $unavailable->values();
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
    @php $showSummary = ! $isPreviousEvent && $available->isNotEmpty(); @endphp
    <div class="tkt-event-layout{{ $showSummary ? '' : ' tkt-event-layout--single' }}" id="tktBooking"
         data-checkout-base="{{ route('front.checkout') }}"
         data-event-id="{{ $event->id }}"
         data-booking-closed="{{ $isBookingClosed ? 1 : 0 }}"
         data-fold-initial="6" data-fold-step="6"
         data-fees='@json($fees)'>

        {{-- Content --}}
        <div class="tkt-event-main">
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

            {{-- Ticket selection: dynamic ticket types in a grid, sold-out ones folded away --}}
            <section class="tkt-tickets-section" aria-labelledby="ticketsTitle">
                <header class="tkt-booking__head">
                    <h2 class="tkt-booking__title" id="ticketsTitle">Select tickets</h2>
                    @if(! $isPreviousEvent && $available->isNotEmpty())
                        <p class="tkt-booking__count">{{ $available->count() }} ticket {{ \Illuminate\Support\Str::plural('type', $available->count()) }} available</p>
                    @endif
                </header>

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
                    @elseif($available->isEmpty())
                        <div class="tkt-alert tkt-alert--danger" role="status" style="margin-bottom:12px">
                            <div><strong>All tickets are sold out.</strong> Check our other events — new ones are added regularly.</div>
                        </div>
                        <span class="tkt-btn tkt-btn--primary tkt-btn--lg is-disabled" aria-disabled="true" role="button">Booking closed</span>
                        <a class="tkt-link" style="display:block;margin:12px 0" href="{{ route('front.events', ['when' => 'upcoming']) }}">See upcoming events</a>
                    @else
                        <div class="tkt-tickets" data-ticket-list>
                            @foreach($available as $i => $ticket)
                                @php
                                    $isCouple = (bool) $ticket->is_couple;
                                    $maxQuantity = $isCouple ? 2 : max(1, (int) ($ticket->max_per_order ?? 10));
                                @endphp
                                <div class="tkt-ticket"
                                     data-id="ticket-{{ $ticket->id }}"
                                     data-ticket="{{ $ticket->name }}"
                                     data-price="{{ number_format($ticket->price, 2, '.', '') }}"
                                     data-max-qty="{{ $maxQuantity }}"
                                     data-is-couple="{{ $isCouple ? 1 : 0 }}"
                                     data-index="{{ $i }}"
                                     data-disabled="0">
                                    <div class="tkt-ticket__info">
                                        <div class="tkt-ticket__name">{{ $ticket->name }}</div>
                                        @if($ticket->description)<div class="tkt-ticket__desc">{{ $ticket->description }}</div>@endif
                                    </div>
                                    <div class="tkt-ticket__price">{{ number_format($ticket->price, 2) }} <small>EGP</small></div>
                                    <div class="tkt-ticket__limit">
                                        <span class="tkt-ticket__avail">Available</span>
                                        @if($isCouple)
                                            <x-tkt.badge variant="warning">Couples only · 2 tickets</x-tkt.badge>
                                        @else
                                            <span>Max {{ $maxQuantity }} per order</span>
                                        @endif
                                    </div>
                                    <div class="tkt-stepper" role="group" aria-label="Quantity for {{ $ticket->name }}">
                                        <button type="button" data-step="-1" aria-label="Decrease quantity of {{ $ticket->name }}" disabled>−</button>
                                        <output aria-live="polite">0</output>
                                        <button type="button" data-step="1" aria-label="Increase quantity of {{ $ticket->name }}">+</button>
                                    </div>
                                </div>
                            @endforeach
                        </div>
                        <div class="tkt-more-row">
                            <button type="button" class="tkt-btn tkt-btn--outline tkt-more" data-more hidden></button>
                            <button type="button" class="tkt-btn tkt-btn--ghost tkt-more" data-less hidden>Show Less</button>
                        </div>
                    @endif

                    @if($unavailable->isNotEmpty())
                        <div class="tkt-soldout">
                            <button type="button" class="tkt-soldout__toggle" id="soldoutToggle" aria-expanded="false" aria-controls="soldoutList">
                                <span>{{ $isBookingClosed ? 'Tickets' : 'Sold Out Tickets' }} ({{ $unavailable->count() }})</span>
                                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="m6 9 6 6 6-6"/></svg>
                            </button>
                            <ul class="tkt-soldout__list" id="soldoutList" hidden>
                                @foreach($unavailable as $ticket)
                                    <li><span class="tkt-soldout__name">{{ $ticket->name }}</span><span class="tkt-soldout__price">{{ number_format($ticket->price, 2) }} EGP</span><x-tkt.badge variant="danger">Sold out</x-tkt.badge></li>
                                @endforeach
                            </ul>
                        </div>
                    @endif
                @endif
            </section>
        </div>

        {{-- Order summary: sticky sidebar on desktop, bottom bar + sheet on mobile (one cart state, one DOM) --}}
        @if($showSummary)
            <aside class="tkt-booking" aria-label="Order summary">
                <div class="tkt-card tkt-booking__card">
                    <h2 class="tkt-booking__title tkt-booking__title--summary">Order Summary</h2>
                    <div class="tkt-summary" id="orderSummary">
                        <div class="tkt-summary__detail" id="summarySheet">
                            <div class="tkt-summary__sheethead"><b>Your order</b><button type="button" class="tkt-summary__close" data-sheet-close aria-label="Close order details">×</button></div>
                            <p class="tkt-summary__empty">No tickets selected</p>
                            <div class="tkt-summary__lines" id="summaryLines"></div>
                            <div class="tkt-summary__clear">
                                <button type="button" class="tkt-summary__clear-btn" data-clear-open>Clear selection</button>
                                <div class="tkt-summary__confirm" data-clear-confirm role="alertdialog" aria-label="Clear selection" hidden>
                                    <span>Remove all selected tickets?</span>
                                    <button type="button" class="tkt-btn tkt-btn--dark tkt-btn--sm" data-clear-yes>Yes, clear</button>
                                    <button type="button" class="tkt-btn tkt-btn--outline tkt-btn--sm" data-clear-no>Keep</button>
                                </div>
                            </div>
                        </div>
                        <div class="tkt-summary__bar">
                            <button type="button" class="tkt-summary__total" id="summaryToggle" aria-expanded="false" aria-controls="summarySheet">
                                <small><span id="summaryCount">No tickets selected</span><svg class="tkt-summary__chev" width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="m6 15 6-6 6 6"/></svg></small>
                                <b id="summaryTotal">0.00 EGP</b>
                            </button>
                            <a href="{{ route('front.checkout') }}" class="tkt-btn tkt-btn--primary tkt-btn--lg tkt-checkout-btn is-disabled" id="checkoutBtn" data-express-checkout aria-disabled="true" tabindex="-1">
                                Continue to Checkout
                                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M5 12h14M13 6l6 6-6 6"/></svg>
                            </a>
                        </div>
                        <p class="tkt-summary__note">No account needed · Promo codes and the final amount are confirmed at checkout</p>
                    </div>
                    <div class="tkt-sheet-backdrop" id="sheetBackdrop" hidden></div>
                </div>
            </aside>
        @endif
    </div>
</div>
@endsection

@push('scripts')
    <script src="{{ asset('js/tkt-booking.js') }}?v={{ filemtime(public_path('js/tkt-booking.js')) }}"></script>
@endpush
