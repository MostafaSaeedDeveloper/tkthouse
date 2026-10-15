@extends('front.layout.app')

@section('title', 'Find your next live experience')

@section('content')
    @php
        $heroEvent = $featuredEvents->first();
        $heroImage = $heroEvent ? ($heroEvent->event_banner_url ?: $heroEvent->cover_image_url) : null;
    @endphp

    {{-- Hero --}}
    <section class="tkt-hero">
        @if($heroImage)
            <div class="tkt-media tkt-hero__media">
                <img class="tkt-img" src="{{ $heroImage }}" alt="" fetchpriority="high">
            </div>
        @endif
        <div class="tkt-container">
            <div class="tkt-hero__inner">
                <div class="tkt-eyebrow tkt-hero__eyebrow">Live events. Bigger together.</div>
                <h1 class="tkt-hero__title">Find your next <em>experience.</em></h1>
                <p class="tkt-hero__lead">Discover live events and book your tickets in minutes — simple, secure and made for the crowd.</p>

                <form class="tkt-hero__search" method="GET" action="{{ route('front.events') }}" role="search">
                    <input type="search" name="q" placeholder="Search events or venues" aria-label="Search events" maxlength="120">
                    <button type="submit" class="tkt-btn tkt-btn--primary" aria-label="Search">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><circle cx="11" cy="11" r="7"/><path d="m21 21-4.3-4.3"/></svg>
                        <span>Search</span>
                    </button>
                </form>

                <div class="tkt-hero__cta">
                    <a href="{{ route('front.events') }}" class="tkt-btn tkt-btn--outline-light">Browse all events</a>
                    @if($heroEvent)
                        <span class="tkt-hero__featured">Next up: <a href="{{ route('front.events.show', $heroEvent) }}">{{ $heroEvent->name }}</a></span>
                    @endif
                </div>
            </div>
        </div>
    </section>

    {{-- Upcoming --}}
    <section class="tkt-section" id="upcoming">
        <div class="tkt-container">
            <x-tkt.section-head title="Upcoming events" sub="Grab your tickets before they sell out." :href="route('front.events', ['when' => 'upcoming'])" />

            <div class="tkt-grid tkt-grid--events">
                @forelse($upcomingEvents as $event)
                    <x-tkt.event-card :event="$event" />
                @empty
                    <x-tkt.empty-state title="No upcoming events yet" text="New events are on the way — check back soon." icon="ticket">
                        <a class="tkt-btn tkt-btn--dark" href="{{ route('front.events') }}">Browse all events</a>
                    </x-tkt.empty-state>
                @endforelse
            </div>
        </div>
    </section>

    {{-- Previous --}}
    @if($previousEvents->isNotEmpty())
        <section class="tkt-section tkt-section--surface" id="previous">
            <div class="tkt-container">
                <x-tkt.section-head title="Previous events" sub="A look back at what you missed." :href="route('front.events', ['when' => 'previous'])" />

                <div class="tkt-grid tkt-grid--past">
                    @foreach($previousEvents as $event)
                        <x-tkt.event-card :event="$event" :past="true" />
                    @endforeach
                </div>
            </div>
        </section>
    @endif
@endsection
