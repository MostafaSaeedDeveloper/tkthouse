@extends('front.layout.master')

@section('content')
<div class="sub-banner">
    <div class="container">
        <h6>TKTHouse Events</h6>
        <p>Browse currently available techno events and reserve your spot.</p>
    </div>
</div>

<div class="kode_content_wrap">
    <section>
        <div class="container">
            <div class="row">
                @for ($i = 0; $i < 6; $i++)
                    <div class="col-md-4 col-sm-6">
                        <div class="msl-concert-list">
                            <figure><img src="{{ asset('extra-images/event4.jpg') }}" alt="Techno Event"></figure>
                            <div class="text-overflow" style="padding: 20px;">
                                <h4 class="concert-title">
                                    <a href="{{ route('events.show', ['event' => 'techno-pulse']) }}">Techno Pulse Night</a>
                                </h4>
                                <div class="concert-meta">
                                    <div class="concert-info"><b>Date:</b> <span>12 March 2026</span></div>
                                    <div class="concert-info"><b>Location:</b> <span>Cairo</span></div>
                                </div>
                                <a class="btn-1" href="{{ route('events.show', ['event' => 'techno-pulse']) }}">View Event</a>
                            </div>
                        </div>
                    </div>
                @endfor
            </div>
        </div>
    </section>
</div>
@endsection
