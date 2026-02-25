@extends('front.layout.master')

@section('content')
    <div class="banner_slider">
        <div class="slide center-align">
            <img src="extra-images/kf_slide_img14.jpg" alt="TKT House Techno Event">
            <div class="banner_content container">
                <div class="b_title animated">TKT HOUSE TECHNO NIGHTS</div>
                <p class="animated">Book verified tickets for premium techno parties, warehouse nights, and festival stages across the region.</p>
                <a href="{{ route('front.events') }}" class="btn_normal border_btn animated">VIEW EVENTS</a>
                <a href="{{ route('front.events.show') }}" class="btn_normal border_btn animated">BOOK NOW</a>
            </div>
        </div>
    </div>

    <section>
        <div class="container">
            <div class="row">
                <div class="col-md-12">
                    <div class="msl-eventlist2-wrap mg-40">
                        <div class="msl-black title-style-2">
                            <div class="msl-heading light-color">
                                <h5><span>Upcoming TKT House Events</span></h5>
                            </div>
                        </div>
                        <div class="msl-eventlist2-slider bottom-arrow msl-black">
                            <div>
                                <div class="msl-eventlist2">
                                    <figure><img src="extra-images/black-img/event-list6.jpg" alt="Underground Pulse"></figure>
                                    <div class="eventlist2-heading">
                                        <h5><a href="{{ route('front.events.show') }}">Underground Pulse</a></h5>
                                        <div class="evnt-tag">
                                            <a href="#">Cairo</a>
                                            <a href="#">Warehouse District</a>
                                            <a href="#">22 Mar 2026</a>
                                        </div>
                                    </div>
                                    <div class="eventlist2-date">
                                        <h6>10:00 PM <span>From $35</span></h6>
                                    </div>
                                    <div class="eventlist2-link">
                                        <a class="btn-1" href="{{ route('front.events.show') }}">Buy Tickets</a>
                                    </div>
                                </div>
                            </div>
                            <div>
                                <div class="msl-eventlist2">
                                    <figure><img src="extra-images/black-img/event-list7.jpg" alt="Bassline Temple"></figure>
                                    <div class="eventlist2-heading">
                                        <h5><a href="{{ route('front.events.show') }}">Bassline Temple</a></h5>
                                        <div class="evnt-tag">
                                            <a href="#">Alexandria</a>
                                            <a href="#">Seafront Arena</a>
                                            <a href="#">12 Apr 2026</a>
                                        </div>
                                    </div>
                                    <div class="eventlist2-date">
                                        <h6>9:30 PM <span>From $40</span></h6>
                                    </div>
                                    <div class="eventlist2-link">
                                        <a class="btn-1" href="{{ route('front.events.show') }}">Buy Tickets</a>
                                    </div>
                                </div>
                            </div>
                            <div>
                                <div class="msl-eventlist2">
                                    <figure><img src="extra-images/black-img/event-list8.jpg" alt="Neon Frequency"></figure>
                                    <div class="eventlist2-heading">
                                        <h5><a href="{{ route('front.events.show') }}">Neon Frequency</a></h5>
                                        <div class="evnt-tag">
                                            <a href="#">Giza</a>
                                            <a href="#">Open Air Dome</a>
                                            <a href="#">30 Apr 2026</a>
                                        </div>
                                    </div>
                                    <div class="eventlist2-date">
                                        <h6>11:00 PM <span>From $45</span></h6>
                                    </div>
                                    <div class="eventlist2-link">
                                        <a class="btn-1" href="{{ route('front.events.show') }}">Buy Tickets</a>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>
@endsection
