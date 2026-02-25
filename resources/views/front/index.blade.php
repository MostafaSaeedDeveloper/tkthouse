@extends('front.layout.master')

@section('content')
<div class="banner_slider">
    <div class="slide center-align">
        <img src="{{ asset('extra-images/main-slide5.jpg') }}" alt="TKTHouse Banner">
        <div class="banner_content container">
            <div class="b_title animated">TKTHouse - Your Techno Event Hub</div>
            <p class="animated">Discover top techno nights and book your tickets in seconds from any device.</p>
            <a href="{{ route('events.index') }}" class="btn_normal border_btn animated">Book Tickets</a>
            <a href="{{ route('about') }}" class="btn_normal border_btn animated">Learn More</a>
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
                            <h5><span>Upcoming Events</span></h5>
                        </div>
                    </div>
                    <div class="msl-eventlist2-slider bottom-arrow msl-black">
                        @for ($i = 0; $i < 4; $i++)
                            <div>
                                <div class="msl-eventlist2">
                                    <figure><img src="{{ asset('extra-images/event6.jpg') }}" alt="TKTHouse Event"></figure>
                                    <div class="eventlist2-heading">
                                        <h5><a href="{{ route('events.show', ['event' => 'techno-pulse']) }}">Techno Pulse Night</a></h5>
                                        <div class="evnt-tag">
                                            <a href="#">Techno</a>
                                            <a href="#">Live</a>
                                            <a href="#">Cairo</a>
                                        </div>
                                    </div>
                                    <div class="eventlist2-date">
                                        <h6>12 March <span>9:00PM</span></h6>
                                    </div>
                                    <div class="eventlist2-link">
                                        <a class="btn-1" href="{{ route('events.show', ['event' => 'techno-pulse']) }}">View & Book</a>
                                    </div>
                                </div>
                            </div>
                        @endfor
                    </div>
                </div>
            </div>
        </div>
    </div>
</section>
@endsection
