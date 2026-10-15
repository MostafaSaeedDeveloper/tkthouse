@extends('front.layout.app')

@section('title', 'Events')

@section('content')
    @php
        $hasSearch = $search !== '';
        $tabUrl = fn (string $value) => route('front.events', array_filter(['q' => $search, 'when' => $value === 'all' ? null : $value]));
        $showUpcoming = $when !== 'previous';
        $showPrevious = $when !== 'upcoming';
    @endphp

    <div class="tkt-page-head">
        <div class="tkt-container">
            <div class="tkt-page-head__top">
                <div>
                    <div class="tkt-eyebrow">TKT House</div>
                    <h1 class="tkt-h1">Events</h1>
                </div>
                <form class="tkt-search" method="GET" action="{{ route('front.events') }}" role="search">
                    @if($when !== 'all')<input type="hidden" name="when" value="{{ $when }}">@endif
                    <div class="tkt-input-group">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><circle cx="11" cy="11" r="7"/><path d="m21 21-4.3-4.3"/></svg>
                        <input class="tkt-input @error('q') is-invalid @enderror" type="search" name="q" value="{{ $search }}" placeholder="Search by event or location" aria-label="Search events" maxlength="120">
                    </div>
                    <button type="submit" class="tkt-btn tkt-btn--dark">Search</button>
                </form>
            </div>
            <nav class="tkt-segment" aria-label="Filter events">
                <a href="{{ $tabUrl('all') }}" class="{{ $when === 'all' ? 'is-active' : '' }}">All</a>
                <a href="{{ $tabUrl('upcoming') }}" class="{{ $when === 'upcoming' ? 'is-active' : '' }}">Upcoming</a>
                <a href="{{ $tabUrl('previous') }}" class="{{ $when === 'previous' ? 'is-active' : '' }}">Previous</a>
            </nav>
        </div>
    </div>

    <section class="tkt-section tkt-section--tight">
        <div class="tkt-container">
            @if($hasSearch)
                <p class="tkt-results-note">Showing results for “<strong>{{ $search }}</strong>”. <a href="{{ route('front.events', array_filter(['when' => $when === 'all' ? null : $when])) }}">Clear search</a></p>
            @endif

            @if($showUpcoming)
                <div class="tkt-listing-block">
                    <x-tkt.section-head title="Upcoming events" />
                    <div class="tkt-grid tkt-grid--events">
                        @forelse($events as $event)
                            <x-tkt.event-card :event="$event" />
                        @empty
                            @if($hasSearch)
                                <x-tkt.empty-state icon="search" title="No upcoming events match your search" text="Try a different name or location." />
                            @else
                                <x-tkt.empty-state title="No upcoming events yet" text="New events are on the way — check back soon." />
                            @endif
                        @endforelse
                    </div>
                    {{ $events->links('vendor.pagination.tkt') }}
                </div>
            @endif

            @if($showPrevious)
                <div class="tkt-listing-block">
                    <x-tkt.section-head title="Previous events" />
                    <div class="tkt-grid tkt-grid--past">
                        @forelse($previousEvents as $event)
                            <x-tkt.event-card :event="$event" :past="true" />
                        @empty
                            @if($hasSearch)
                                <x-tkt.empty-state icon="search" title="No previous events match your search" text="Try a different name or location." />
                            @else
                                <x-tkt.empty-state icon="clock" title="No previous events" text="Finished events will appear here once their date has passed." />
                            @endif
                        @endforelse
                    </div>
                    {{ $previousEvents->links('vendor.pagination.tkt') }}
                </div>
            @endif
        </div>
    </section>
@endsection
