@extends('front.layout.master')

@section('content')
    <div class="sub-banner">
        <div class="container">
            <h6>TKT House Events</h6>
            <p>Discover upcoming techno parties and reserve your ticket.</p>
        </div>
    </div>

    <div class="kode_content_wrap">
        <section>
            <div class="container">
                <div class="msl-concert-list">
                    <figure><img src="extra-images/concert1.jpg" alt="Underground Pulse"></figure>
                    <div class="text-overflow">
                        <h4 class="concert-title"><a href="{{ route('front.events.show') }}">Underground Pulse: Warehouse Edition</a></h4>
                        <div class="concert-meta">
                            <div class="concert-info"><b>Date:</b> <span>22 March, 2026</span></div>
                            <div class="concert-info"><b>Time:</b> <span>10:00 PM</span></div>
                            <div class="concert-info concert-location"><b>Location:</b> <span>Cairo Warehouse District</span></div>
                        </div>
                        <p>A deep and driving techno experience with headline DJs, immersive lights, and full-night production.</p>
                        <a class="btn-1" href="{{ route('front.events.show') }}">View Details</a>
                    </div>
                </div>

                <div class="msl-concert-list">
                    <figure><img src="extra-images/concert2.jpg" alt="Bassline Temple"></figure>
                    <div class="text-overflow">
                        <h4 class="concert-title"><a href="{{ route('front.events.show') }}">Bassline Temple: Seafront Session</a></h4>
                        <div class="concert-meta">
                            <div class="concert-info"><b>Date:</b> <span>12 April, 2026</span></div>
                            <div class="concert-info"><b>Time:</b> <span>9:30 PM</span></div>
                            <div class="concert-info concert-location"><b>Location:</b> <span>Alexandria Seafront Arena</span></div>
                        </div>
                        <p>Open-air vibes, curated lineups, and high-energy techno performances by regional and international artists.</p>
                        <a class="btn-1" href="{{ route('front.events.show') }}">View Details</a>
                    </div>
                </div>
            </div>
        </section>
    </div>
@endsection
