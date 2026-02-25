@extends('front.layout.master')

@section('content')
<div class="sub-banner">
    <div class="container">
        <h6>Event Details</h6>
        <p>For now, any event URL shows this same template until the admin panel is connected to live event data.</p>
    </div>
</div>

<div class="kode_content_wrap">
    <section>
        <div class="container">
            <div class="msl-concert-list">
                <figure><img src="{{ asset('extra-images/event6.jpg') }}" alt="Techno Pulse Night"></figure>
                <div class="text-overflow" style="padding: 25px;">
                    <h3>Techno Pulse Night</h3>
                    <p><strong>Event URL Slug:</strong> {{ $eventSlug }}</p>
                    <p>
                        A premium techno night with immersive visuals and top-tier DJs.
                        Entry is handled through your TKTHouse e-ticket.
                    </p>
                    <ul>
                        <li><strong>Date:</strong> 12 March 2026</li>
                        <li><strong>Time:</strong> 09:00 PM</li>
                        <li><strong>Venue:</strong> Cairo Arena</li>
                        <li><strong>Ticket Price:</strong> Starting from EGP 450</li>
                    </ul>
                    <a class="btn-1" href="#">Book Now (Demo)</a>
                </div>
            </div>
        </div>
    </section>
</div>
@endsection
