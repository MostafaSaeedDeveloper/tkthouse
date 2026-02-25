@extends('front.layout.master')

@section('content')
    <div class="sub-banner">
        <div class="container">
            <h6>TKT House Techno Night</h6>
            <p>Event details and ticket options.</p>
        </div>
    </div>

    <div class="kode_content_wrap">
        <section>
            <div class="container">
                <div class="msl-concert-list">
                    <figure><img src="extra-images/event-detail1.jpg" alt="TKT House Techno Night"></figure>
                    <div class="text-overflow">
                        <h4 class="concert-title"><a href="#">Underground Pulse: Warehouse Edition</a></h4>
                        <div class="concert-meta">
                            <div class="concert-info"><b>Date:</b> <span>22 March, 2026</span></div>
                            <div class="concert-info"><b>Time:</b> <span>10:00 PM - 6:00 AM</span></div>
                            <div class="concert-info concert-location"><b>Location:</b> <span>Cairo Warehouse District</span></div>
                        </div>
                        <p>TKT House presents a full-scale techno night with warehouse visuals, precision sound engineering, and a lineup built for underground electronic music lovers.</p>
                    </div>
                </div>

                <div class="ticket-section">
                    <div class="msl-black">
                        <div class="msl-heading light-color">
                            <h5><span>Book Your Tickets</span></h5>
                        </div>
                    </div>
                    <div class="tickets">
                        <ul class="kode-tickets-title">
                            <li>Ticket Type</li>
                            <li>Price</li>
                            <li>Benefits</li>
                            <li>Booking</li>
                        </ul>
                        <ul class="ticket-column secnd">
                            <li><span>Early Access</span></li>
                            <li><span>$35</span></li>
                            <li><span>Entry before 11 PM</span></li>
                            <li><a href="#">Reserve</a></li>
                        </ul>
                        <ul class="ticket-column">
                            <li><span>General Admission</span></li>
                            <li><span>$45</span></li>
                            <li><span>Full night access</span></li>
                            <li><a href="#">Reserve</a></li>
                        </ul>
                        <ul class="ticket-column secnd">
                            <li><span>VIP Deck</span></li>
                            <li><span>$80</span></li>
                            <li><span>Fast track + lounge zone</span></li>
                            <li><a href="#">Reserve</a></li>
                        </ul>
                    </div>
                </div>
            </div>
        </section>
    </div>
@endsection
