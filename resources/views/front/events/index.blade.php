@extends('front.layout.master')

@section('content')
    <div class="sub-banner">
        <div class="container">
            <h6>Events</h6>
            <p>We are a techno events company focused on unforgettable nights and secure ticket booking.</p>
        </div>
    </div>

    <div class="kode_content_wrap">
        <section>
            <div class="container">
                @forelse($events as $event)
                    <div class="msl-concert-list">
                        <figure>
                            <img src="{{ $event->cover_image_url ?? asset('extra-images/concert1.jpg') }}" alt="{{ $event->name }}">
                        </figure>
                        <div class="text-overflow">
                            <h4 class="concert-title">
                                <a href="{{ route('front.events.show', $event) }}">{{ $event->name }}</a>
                            </h4>
                            <div class="concert-meta">
                                <div class="concert-info">
                                    <b>Date:</b>
                                    <span>{{ $event->event_date->format('d F, Y') }}</span>
                                </div>
                                <div class="concert-info">
                                    <b>Time:</b>
                                    <span>{{ \Carbon\Carbon::parse($event->event_time)->format('g:iA') }}</span>
                                </div>
                                <div class="concert-info concert-location">
                                    <b>Location:</b>
                                    <span>{{ $event->location }}</span>
                                </div>
                            </div>
                            <a class="btn-1 theme-bg" href="{{ route('front.events.show', $event) }}">Buy Ticket</a>
                        </div>
                    </div>
                @empty
                    <div class="msl-concert-list">
                        <figure><img src="{{ asset('extra-images/concert1.jpg') }}" alt="No events"></figure>
                        <div class="text-overflow">
                            <h4 class="concert-title">No upcoming events available right now.</h4>
                            <div class="concert-meta">
                                <div class="concert-info concert-location">
                                    <b>Note:</b>
                                    <span>Please check back later for new event announcements.</span>
                                </div>
                            </div>
                            <a class="btn-1 theme-bg" href="{{ route('front.home') }}">Back to Home</a>
                        </div>
                    </div>
                @endforelse

                @if($events->hasPages())
                    <ul class="pagination">
                        @if($events->onFirstPage())
                            <li class="disabled">
                                <span aria-hidden="true"><i class="fa fa-angle-left"></i>PREV</span>
                            </li>
                        @else
                            <li>
                                <a href="{{ $events->previousPageUrl() }}" aria-label="Previous">
                                    <span aria-hidden="true"><i class="fa fa-angle-left"></i>PREV</span>
                                </a>
                            </li>
                        @endif

                        @foreach($events->getUrlRange(1, $events->lastPage()) as $page => $url)
                            <li class="{{ $events->currentPage() === $page ? 'active' : '' }}"><a href="{{ $url }}">{{ $page }}</a></li>
                        @endforeach

                        @if($events->hasMorePages())
                            <li>
                                <a href="{{ $events->nextPageUrl() }}" aria-label="Next">
                                    <span aria-hidden="true">Next<i class="fa fa-angle-right"></i></span>
                                </a>
                            </li>
                        @else
                            <li class="disabled">
                                <span aria-hidden="true">Next<i class="fa fa-angle-right"></i></span>
                            </li>
                        @endif
                    </ul>
                @endif
            </div>
        </section>
    </div>
@endsection
