@extends('front.layout.master')

@section('content')
    <div class="sub-banner">
        <div class="container">
            <h6>TKT House Events</h6>
            <p>Book upcoming techno events with secure ticket access.</p>
        </div>
    </div>

    <div class="kode_content_wrap">
        <section>
            <div class="container">
                <div class="msl-concert-list">
                    <figure><img src="extra-images/concert1.jpg" alt="Underground Pulse"></figure>
                    <div class="text-overflow">
                        <h4 class="concert-title">
                            <a href="{{ route('front.events.show') }}">Underground Pulse</a>
                        </h4>
                        <div class="concert-meta">
                            <div class="concert-info">
                                <b>Date:</b>
                                <span>22 March, 2026</span>
                            </div>
                            <div class="concert-info">
                                <b>Phone:</b>
                                <span>+20 100 555 8899</span>
                            </div>
                            <div class="concert-info concert-location">
                                <b>Location:</b>
                                <span>Cairo Warehouse District</span>
                            </div>
                        </div>
                        <a class="btn-1 theme-bg" href="{{ route('front.events.show') }}">Buy Ticket</a>
                    </div>
                </div>

                <div class="msl-concert-list">
                    <figure><img src="extra-images/concert2.jpg" alt="Bassline Temple"></figure>
                    <div class="text-overflow">
                        <h4 class="concert-title">
                            <a href="{{ route('front.events.show') }}">Bassline Temple</a>
                        </h4>
                        <div class="concert-meta">
                            <div class="concert-info">
                                <b>Date:</b>
                                <span>12 April, 2026</span>
                            </div>
                            <div class="concert-info">
                                <b>Phone:</b>
                                <span>+20 100 555 8899</span>
                            </div>
                            <div class="concert-info concert-location">
                                <b>Location:</b>
                                <span>Alexandria Seafront Arena</span>
                            </div>
                        </div>
                        <a class="btn-1 theme-bg" href="{{ route('front.events.show') }}">Buy Ticket</a>
                    </div>
                </div>

                <div class="msl-concert-list">
                    <figure><img src="extra-images/concert3.jpg" alt="Neon Frequency"></figure>
                    <div class="text-overflow">
                        <h4 class="concert-title">
                            <a href="{{ route('front.events.show') }}">Neon Frequency</a>
                        </h4>
                        <div class="concert-meta">
                            <div class="concert-info">
                                <b>Date:</b>
                                <span>30 April, 2026</span>
                            </div>
                            <div class="concert-info">
                                <b>Phone:</b>
                                <span>+20 100 555 8899</span>
                            </div>
                            <div class="concert-info concert-location">
                                <b>Location:</b>
                                <span>Giza Open Air Dome</span>
                            </div>
                        </div>
                        <a class="btn-1 theme-bg" href="{{ route('front.events.show') }}">Buy Ticket</a>
                    </div>
                </div>
            </div>
        </section>
    </div>
@endsection
