@extends('front.layout.master')

@section('content')
    <div class="kode_content_wrap">
        <section>
            <div class="container">
                <div class="msl-concert-list">
                    <figure><img src="extra-images/concert1.jpg" alt="KODEFOREST"></figure>
                    <div class="text-overflow">
                        <h4 class="concert-title">
                            <a href="{{ route('front.events.show') }}">Lorem Ipsum Proin gravida nibh vel velit auctor aliquet</a>
                        </h4>
                        <div class="concert-meta">
                            <div class="concert-info">
                                <b>Date:</b>
                                <span>25 July, 2018</span>
                            </div>
                            <div class="concert-info">
                                <b>Phone:</b>
                                <span>06 511 21022</span>
                            </div>
                            <div class="concert-info concert-location">
                                <b>Location:</b>
                                <span>Level 13, 2 Elizabeth St, Melbourne Victoria 3000 Australia</span>
                            </div>
                        </div>
                        <a class="btn-1 theme-bg" href="{{ route('front.events.show') }}">Buy Ticket</a>
                    </div>
                </div>

                <div class="msl-concert-list">
                    <figure><img src="extra-images/concert2.jpg" alt="KODEFOREST"></figure>
                    <div class="text-overflow">
                        <h4 class="concert-title">
                            <a href="{{ route('front.events.show') }}">Lorem Ipsum Proin gravida nibh vel velit auctor aliquet</a>
                        </h4>
                        <div class="concert-meta">
                            <div class="concert-info">
                                <b>Date:</b>
                                <span>25 July, 2018</span>
                            </div>
                            <div class="concert-info">
                                <b>Phone:</b>
                                <span>06 511 21022</span>
                            </div>
                            <div class="concert-info concert-location">
                                <b>Location:</b>
                                <span>Level 13, 2 Elizabeth St, Melbourne Victoria 3000 Australia</span>
                            </div>
                        </div>
                        <a class="btn-1 theme-bg" href="{{ route('front.events.show') }}">Buy Ticket</a>
                    </div>
                </div>

                <div class="msl-concert-list">
                    <figure><img src="extra-images/concert3.jpg" alt="KODEFOREST"></figure>
                    <div class="text-overflow">
                        <h4 class="concert-title">
                            <a href="{{ route('front.events.show') }}">Lorem Ipsum Proin gravida nibh vel velit auctor aliquet</a>
                        </h4>
                        <div class="concert-meta">
                            <div class="concert-info">
                                <b>Date:</b>
                                <span>25 July, 2018</span>
                            </div>
                            <div class="concert-info">
                                <b>Phone:</b>
                                <span>06 511 21022</span>
                            </div>
                            <div class="concert-info concert-location">
                                <b>Location:</b>
                                <span>Level 13, 2 Elizabeth St, Melbourne Victoria 3000 Australia</span>
                            </div>
                        </div>
                        <a class="btn-1 theme-bg" href="{{ route('front.events.show') }}">Buy Ticket</a>
                    </div>
                </div>
            </div>
        </section>
    </div>
@endsection
