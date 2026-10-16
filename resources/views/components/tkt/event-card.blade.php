@props(['event', 'past' => false])
@php
    $isSoldOut = $event->status === 'sold_out';
    $multiDay = $event->end_date && $event->end_date->ne($event->event_date);
    $time = $event->event_time ? \Carbon\Carbon::parse($event->event_time)->format('g:i A') : null;
    $cover = $event->cover_image_url ?: $event->event_banner_url;
@endphp
<a href="{{ route('front.events.show', $event) }}" class="tkt-event-card {{ $past ? 'tkt-event-card--past' : '' }}" aria-label="{{ $event->name }}">
    <div class="tkt-media tkt-event-card__media {{ $cover ? '' : 'is-empty' }}">
        @if($cover)
            <img class="tkt-img" src="{{ $cover }}" alt="{{ $event->name }}" loading="lazy">
        @endif
        <div class="tkt-event-card__date">
            <b>{{ $event->event_date->format('d') }}@if($multiDay)–{{ $event->end_date->format('d') }}@endif</b>
            <span>{{ $event->event_date->format('M') }}</span>
        </div>
        <div class="tkt-event-card__status">
            @if($past)
                <x-tkt.badge variant="glass">Ended</x-tkt.badge>
            @elseif($isSoldOut)
                <x-tkt.badge variant="danger">Sold out</x-tkt.badge>
            @else
                <x-tkt.badge variant="primary">On sale</x-tkt.badge>
            @endif
        </div>
    </div>
    <div class="tkt-event-card__body">
        <h3 class="tkt-event-card__title">{{ $event->name }}</h3>
        <ul class="tkt-event-card__meta">
            <li>
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><rect x="3" y="4" width="18" height="18" rx="2"/><path d="M16 2v4M8 2v4M3 10h18"/></svg>
                <span>{{ $event->formatted_date_range }}@if($time) · {{ $time }}@endif</span>
            </li>
            @if($event->location)
                <li>
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M21 10c0 7-9 13-9 13S3 17 3 10a9 9 0 0 1 18 0z"/><circle cx="12" cy="10" r="3"/></svg>
                    <span>{{ $event->location }}</span>
                </li>
            @endif
        </ul>
        @unless($past)
            <div class="tkt-event-card__cta">
                <span class="tkt-btn {{ $isSoldOut ? 'tkt-btn--outline' : 'tkt-btn--primary' }}">{{ $isSoldOut ? 'View details' : 'Get tickets' }}</span>
            </div>
        @endunless
    </div>
</a>
