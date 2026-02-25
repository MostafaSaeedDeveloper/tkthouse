@extends('front.layout.master')

@section('content')
<div class="sub-banner">
    <div class="container">
        <h6>About</h6>
        <p>We are a specialized company for techno events and ticket booking experiences.</p>
    </div>
</div>

<div class="kode_content_wrap">
    <section>
        <div class="container">
            <div class="row">
                <div class="col-md-8 col-sm-12">
                    <h3>Our Mission</h3>
                    <p>
                        At TKTHouse, we connect electronic music fans with unforgettable techno nights.
                        Our goal is to deliver a trusted platform for event discovery, quick booking, and easy ticket access.
                    </p>
                    <h4>What We Offer</h4>
                    <ul>
                        <li>Professional techno event organization.</li>
                        <li>Fast online ticket booking in a few simple steps.</li>
                        <li>Clear event information in one place.</li>
                    </ul>
                </div>
                <div class="col-md-4 col-sm-12">
                    <figure>
                        <img src="{{ asset('extra-images/featured3.jpg') }}" alt="TKTHouse Team">
                    </figure>
                </div>
            </div>
        </div>
    </section>
</div>
@endsection
