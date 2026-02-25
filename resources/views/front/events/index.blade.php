@extends('front.layout.master')

@section('content')
    <div class="sub-banner">
        <div class="container">
            <h6>TKT House Event Listings</h6>
            <p>Explore upcoming techno nights and reserve your ticket in seconds.</p>
        </div>
    </div>

    <div class="kode_content_wrap">
        <section>
            <div class="container">
                <div class="msl-concert-list">
                    <figure><img src="extra-images/concert1.jpg" alt="TKT House Event"></figure>
                    <div class="text-overflow">
                        <h4 class="concert-title">
                            <a href="{{ route('front.events.show') }}">Underground Pulse: Warehouse Session</a>
                        </h4>
                        <div class="concert-meta">
                            <div class="concert-info">
                                <b>Date:</b>
                                <span>22 March, 2026</span>
                            </div>
                            <div class="concert-info">
                                <b>phone:</b>
                                <span>+20 100 555 8899</span>
                            </div>
                            <div class="concert-info concert-location">
                                <b>Location:</b>
                                <span>Cairo Warehouse District, Egypt</span>
                            </div>
                        </div>
                        <a class="btn-1 theme-bg" href="{{ route('front.events.show') }}">Buy Ticket</a>
                    </div>
                </div>

                <div class="msl-concert-list">
                    <figure><img src="extra-images/concert2.jpg" alt="TKT House Event"></figure>
                    <div class="text-overflow">
                        <h4 class="concert-title">
                            <a href="{{ route('front.events.show') }}">Bassline Temple: Seafront Edition</a>
                        </h4>
                        <div class="concert-meta">
                            <div class="concert-info">
                                <b>Date:</b>
                                <span>12 April, 2026</span>
                            </div>
                            <div class="concert-info">
                                <b>phone:</b>
                                <span>+20 100 555 8899</span>
                            </div>
                            <div class="concert-info concert-location">
                                <b>Location:</b>
                                <span>Alexandria Seafront Arena, Egypt</span>
                            </div>
                        </div>
                        <a class="btn-1 theme-bg" href="{{ route('front.events.show') }}">Buy Ticket</a>
                    </div>
                </div>

                <div class="msl-concert-list">
                    <figure><img src="extra-images/concert6.jpg" alt="TKT House Event"></figure>
                    <div class="text-overflow">
                        <h4 class="concert-title">
                            <a href="{{ route('front.events.show') }}">Neon Frequency: Open Air Dome</a>
                        </h4>
                        <div class="concert-meta">
                            <div class="concert-info">
                                <b>Date:</b>
                                <span>30 April, 2026</span>
                            </div>
                            <div class="concert-info">
                                <b>phone:</b>
                                <span>+20 100 555 8899</span>
                            </div>
                            <div class="concert-info concert-location">
                                <b>Location:</b>
                                <span>Giza Open Air Dome, Egypt</span>
                            </div>
                        </div>
                        <a class="btn-1 theme-bg" href="{{ route('front.events.show') }}">Buy Ticket</a>
                    </div>
                </div>

                <div class="msl-concert-list">
                    <figure><img src="extra-images/concert7.jpg" alt="TKT House Event"></figure>
                    <div class="text-overflow">
                        <h4 class="concert-title">
                            <a href="{{ route('front.events.show') }}">Dark Circuit: Industrial Night</a>
                        </h4>
                        <div class="concert-meta">
                            <div class="concert-info">
                                <b>Date:</b>
                                <span>18 May, 2026</span>
                            </div>
                            <div class="concert-info">
                                <b>phone:</b>
                                <span>+20 100 555 8899</span>
                            </div>
                            <div class="concert-info concert-location">
                                <b>Location:</b>
                                <span>6th October Industrial Zone, Egypt</span>
                            </div>
                        </div>
                        <a class="btn-1 theme-bg" href="{{ route('front.events.show') }}">Buy Ticket</a>
                    </div>
                </div>

                <div class="msl-concert-list">
                    <figure><img src="extra-images/concert8.jpg" alt="TKT House Event"></figure>
                    <div class="text-overflow">
                        <h4 class="concert-title">
                            <a href="{{ route('front.events.show') }}">Pulse Horizon: Closing Festival</a>
                        </h4>
                        <div class="concert-meta">
                            <div class="concert-info">
                                <b>Date:</b>
                                <span>7 June, 2026</span>
                            </div>
                            <div class="concert-info">
                                <b>phone:</b>
                                <span>+20 100 555 8899</span>
                            </div>
                            <div class="concert-info concert-location">
                                <b>Location:</b>
                                <span>North Coast Event Park, Egypt</span>
                            </div>
                        </div>
                        <a class="btn-1 theme-bg" href="{{ route('front.events.show') }}">Buy Ticket</a>
                    </div>
                </div>

                <ul class="pagination">
                    <li>
                        <a aria-label="Previous" href="#">
                            <span aria-hidden="true"><i class="fa fa-angle-left"></i>PREV</span>
                        </a>
                    </li>
                    <li class="active"><a href="#">1</a></li>
                    <li><a href="#">2</a></li>
                    <li><a href="#">3</a></li>
                    <li><a href="#">4</a></li>
                    <li><a href="#">5</a></li>
                    <li>
                        <a aria-label="Next" href="#">
                            <span aria-hidden="true">Next<i class="fa fa-angle-right"></i></span>
                        </a>
                    </li>
                </ul>
            </div>
        </section>
    </div>
@endsection
