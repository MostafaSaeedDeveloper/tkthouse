@extends('front.layout.master')

@section('content')
    <div class="sub-banner">
        <div class="container">
            <h6>Contact TKT House</h6>
            <p>Reach our team for bookings, partnerships, and support.</p>
        </div>
    </div>

    <div class="kode_content_wrap">
        <section>
            <div class="container">
                <div class="row">
                    <div class="col-md-6 col-sm-6">
                        <div class="widget-contact">
                            <h4 class="widget-title2">Send us a message</h4>
                            @if($errors->any())
                                <div style="margin-bottom:14px;background:rgba(248,113,113,.08);border:1px solid rgba(248,113,113,.35);border-radius:8px;padding:10px 12px;color:#fecaca;font-size:12px;">
                                    {{ $errors->first() }}
                                </div>
                            @endif
                            <form method="post" action="{{ route('front.contact.submit') }}" id="commentform" class="light_bg comment-form">
                                @csrf
                                <div class="kode-left-comment-sec">
                                    <div class="kf_commet_field">
                                        <input placeholder="Full Name*" name="author" type="text" size="30" value="{{ old('author') }}" required>
                                    </div>
                                    <div class="kf_commet_field">
                                        <input placeholder="Phone Number*" name="phone" type="text" size="30" value="{{ old('phone') }}" required>
                                    </div>
                                    <div class="kf_commet_field">
                                        <input placeholder="Email Address*" name="email" type="email" size="30" value="{{ old('email') }}" required>
                                    </div>
                                    <div class="kf_commet_field full-width-kode">
                                        <input placeholder="Subject" name="subject" type="text" size="30" value="{{ old('subject') }}">
                                    </div>
                                </div>
                                <div class="kode-textarea">
                                    <textarea placeholder="Tell us how we can help" name="comment" required>{{ old('comment') }}</textarea>
                                </div>
                                <p class="form-submit"><input name="submit" type="submit" class="submit btn-1 theme-bg" value="Send Message"></p>
                            </form>
                        </div>
                    </div>
                    <div class="col-md-6 col-sm-6">
                        <div class="widget-contact social-contact">
                            <h4 class="widget-title2">Direct Contact</h4>
                            <p>TKT House operates techno events and digital ticketing support daily. Contact us for group bookings, VIP packages, sponsorship opportunities, and event logistics.</p>
                            <ul class="kf_contact_meta">
                                <li>
                                    <span class="fa fa-phone"></span>
                                    <p>+20 100 555 8899</p>
                                </li>
                                <li>
                                    <span class="fa fa-envelope"></span>
                                    <p>support@tkthouse.com</p>
                                </li>
                                <li>
                                    <span class="fa fa-map-marker"></span>
                                    <p>New Cairo, Egypt</p>
                                </li>
                            </ul>
                        </div>
                    </div>
                </div>
            </div>
        </section>
    </div>
@endsection
