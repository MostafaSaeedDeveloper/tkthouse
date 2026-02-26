@extends('front.layout.master')

@section('content')



            <div class="banner_slider">
                <div class="slide left-align">
                    <img src="extra-images/kf_slide_img14.jpg" alt="banner img">
                    <div class="banner_content container">
                        <div class="b_title animated">TKTHOUSE TECH EVENTS ARE LIVE!</div>
                        <p class="animated">We organize technology events and provide easy ticket booking for every attendee.</p>
                        <a href="{{ route('front.events') }}" class="btn_normal border_btn animated">BOOK NOW</a>
                        <a href="{{ route('front.events') }}" class="btn_normal border_btn animated ">EXPLORE EVENTS</a>
                    </div>
                </div>
                <div class="slide center-align">
                    <img src="extra-images/kf_slide_img5.jpg" alt="banner img">
                    <div class="banner_content container">
                        <div class="b_title animated">TKTHOUSE TECH EVENTS ARE LIVE!</div>
                        <p class="animated">We organize technology events and provide easy ticket booking for every attendee.</p>
                        <a href="{{ route('front.events') }}" class="btn_normal border_btn animated">BOOK NOW</a>
                        <a href="{{ route('front.events') }}" class="btn_normal border_btn animated ">EXPLORE EVENTS</a>
                    </div>
                </div>
                <div class="slide right-align">
                    <img src="extra-images/kf_slide_img8.jpg" alt="banner img">
                    <div class="banner_content container">
                        <div class="b_title animated">TKTHOUSE TECH EVENTS ARE LIVE!</div>
                        <p class="animated">We organize technology events and provide easy ticket booking for every attendee.</p>
                        <a href="{{ route('front.events') }}" class="btn_normal border_btn animated">BOOK NOW</a>
                        <a href="{{ route('front.events') }}" class="btn_normal border_btn animated ">EXPLORE EVENTS</a>
                    </div>
                </div>
            </div>


            <!--Main Content Wrap Start-->
            <section>

	            <div class="container">
	            	<div class="row">
	            		<div class="col-md-12">
			            <div class="msl-eventlist2-wrap mg-40">
                                <!--Heading Start-->
                                <div class="msl-black title-style-2">
	                                <div class="msl-heading light-color">
	                                    <h5><span>Upcoming TKTHouse Events</span></h5>
	                                </div>
	                            </div>
                                <!--Heading End-->
                                <div class="msl-eventlist2-slider bottom-arrow msl-black">
                                    @forelse($upcomingEvents as $event)
                                        <div>
                                            <!--Event List 2 Strat-->
                                            <div class="msl-eventlist2">
                                                <figure>
                                                    <img src="{{ $event->cover_image_url ?? asset('extra-images/black-img/event-list6.jpg') }}" alt="{{ $event->name }}">
                                                </figure>
                                                <div class="eventlist2-heading">
                                                    <h5><a href="{{ route('front.events.show', $event) }}">{{ $event->name }}</a></h5>
                                                    <div class="evnt-tag">
                                                        <a href="#">TKTHouse</a>
                                                        <a href="#">Tech</a>
                                                        <a href="#">{{ str($event->status)->replace('_', ' ')->title() }}</a>
                                                    </div>
                                                </div>
                                                <div class="eventlist2-date">
                                                    <h6>{{ $event->event_date->format('d M') }} <span>{{ \Carbon\Carbon::parse($event->event_time)->format('g:iA') }}</span></h6>
                                                </div>
                                                <div class="eventlist2-link">
                                                    <a class="btn-1" href="{{ route('front.events.show', $event) }}">Buy Tickets</a>
                                                </div>
                                            </div>
                                            <!--Event List 2 End-->
                                        </div>
                                    @empty
                                        <div>
                                            <div class="msl-eventlist2">
                                                <figure><img src="extra-images/black-img/event-list6.jpg" alt="TKTHouse"></figure>
                                                <div class="eventlist2-heading">
                                                    <h5><a href="{{ route('front.events') }}">No upcoming events yet</a></h5>
                                                    <div class="evnt-tag">
                                                        <a href="#">TKTHouse</a>
                                                        <a href="#">Tech</a>
                                                        <a href="#">Soon</a>
                                                    </div>
                                                </div>
                                                <div class="eventlist2-date">
                                                    <h6>Stay Tuned <span>New dates soon</span></h6>
                                                </div>
                                                <div class="eventlist2-link">
                                                    <a class="btn-1" href="{{ route('front.events') }}">Explore Events</a>
                                                </div>
                                            </div>
                                        </div>
                                    @endforelse
                                </div>
                            </div>

	            		</div>
	            	</div>

	            </div>
            </section>
            <!--Main Content Wrap End-->


@endsection
