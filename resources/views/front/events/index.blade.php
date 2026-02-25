@extends('front.layout.master')

@section('content')
    <div class="sub-banner">
        <div class="container">
            <h6>Event & listing Music Forest HTML Template</h6>
            <p>Praising pain was born and I will give you a complete accountwill give you a complete account</p>
        </div>
    </div>

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
                                <b>phone:</b>
                                <span>06 511 21022</span>
                            </div>
                            <div class="concert-info concert-location">
                                <b>Location:</b>
                                <span>Level 13, 2 Elizabeth St, Melbourne victoria 3000 Australia</span>
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
                                <b>phone:</b>
                                <span>06 511 21022</span>
                            </div>
                            <div class="concert-info concert-location">
                                <b>Location:</b>
                                <span>Level 13, 2 Elizabeth St, Melbourne victoria 3000 Australia</span>
                            </div>
                        </div>
                        <a class="btn-1 theme-bg" href="{{ route('front.events.show') }}">Buy Ticket</a>
                    </div>
                </div>

                <div class="msl-concert-list">
                    <figure><img src="extra-images/concert6.jpg" alt="KODEFOREST"></figure>
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
                                <b>phone:</b>
                                <span>06 511 21022</span>
                            </div>
                            <div class="concert-info concert-location">
                                <b>Location:</b>
                                <span>Level 13, 2 Elizabeth St, Melbourne victoria 3000 Australia</span>
                            </div>
                        </div>
                        <a class="btn-1 theme-bg" href="{{ route('front.events.show') }}">Buy Ticket</a>
                    </div>
                </div>

                <div class="msl-concert-list">
                    <figure><img src="extra-images/concert7.jpg" alt="KODEFOREST"></figure>
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
                                <b>phone:</b>
                                <span>06 511 21022</span>
                            </div>
                            <div class="concert-info concert-location">
                                <b>Location:</b>
                                <span>Level 13, 2 Elizabeth St, Melbourne victoria 3000 Australia</span>
                            </div>
                        </div>
                        <a class="btn-1 theme-bg" href="{{ route('front.events.show') }}">Buy Ticket</a>
                    </div>
                </div>

                <div class="msl-concert-list">
                    <figure><img src="extra-images/concert8.jpg" alt="KODEFOREST"></figure>
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
                                <b>phone:</b>
                                <span>06 511 21022</span>
                            </div>
                            <div class="concert-info concert-location">
                                <b>Location:</b>
                                <span>Level 13, 2 Elizabeth St, Melbourne victoria 3000 Australia</span>
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
