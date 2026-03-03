@extends('front.layout.master')

@section('content')
    <style>
        .contact-wrap {
            margin-top: 30px;
            margin-bottom: 50px;
        }

        .contact-card {
            background: #101015;
            border: 1px solid rgba(255, 255, 255, 0.08);
            border-radius: 14px;
            padding: 28px;
            box-shadow: 0 20px 40px rgba(0, 0, 0, 0.35);
            height: 100%;
        }

        .contact-card .widget-title2 {
            color: #fff;
            margin-bottom: 8px;
        }

        .contact-subtitle {
            color: #9ca3af;
            font-size: 13px;
            margin-bottom: 18px;
        }

        .contact-grid {
            display: grid;
            gap: 12px;
            grid-template-columns: repeat(2, minmax(0, 1fr));
        }

        .contact-grid .full {
            grid-column: 1 / -1;
        }

        .contact-field label {
            display: block;
            color: #d1d5db;
            font-size: 12px;
            margin-bottom: 6px;
            font-weight: 600;
        }

        .contact-field input,
        .contact-field textarea {
            width: 100%;
            background: #17171f;
            border: 1px solid rgba(255, 255, 255, 0.1);
            border-radius: 10px;
            color: #fff;
            padding: 11px 12px;
            font-size: 13px;
            transition: all .2s ease;
        }

        .contact-field textarea {
            min-height: 140px;
            resize: vertical;
        }

        .contact-field input:focus,
        .contact-field textarea:focus {
            outline: none;
            border-color: #f5b800;
            box-shadow: 0 0 0 3px rgba(245, 184, 0, 0.1);
        }

        .contact-submit {
            width: 100%;
            border: 0;
            border-radius: 10px;
            padding: 13px 18px;
            font-weight: 700;
            font-size: 13px;
            letter-spacing: .3px;
        }

        .contact-info-list {
            margin-top: 16px;
        }

        .contact-info-list li {
            display: flex;
            align-items: flex-start;
            gap: 12px;
            padding: 10px 0;
            border-bottom: 1px solid rgba(255, 255, 255, 0.06);
        }

        .contact-info-list li:last-child {
            border-bottom: 0;
        }

        .contact-info-list span {
            width: 34px;
            height: 34px;
            border-radius: 50%;
            background: rgba(245, 184, 0, 0.12);
            color: #f5b800;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            flex-shrink: 0;
        }

        .contact-info-list p {
            margin: 6px 0 0;
            color: #f3f4f6;
        }

        @media (max-width: 767px) {
            .contact-grid {
                grid-template-columns: 1fr;
            }
        }
    </style>

    <div class="sub-banner">
        <div class="container">
            <h6>Contact TKT House</h6>
            <p>Reach our team for bookings, partnerships, and support.</p>
        </div>
    </div>

    <div class="kode_content_wrap contact-wrap">
        <section>
            <div class="container">
                <div class="row">
                    <div class="col-md-7 col-sm-12">
                        <div class="widget-contact contact-card">
                            <h4 class="widget-title2">Send us a message</h4>
                            <p class="contact-subtitle">Fill in your details and our team will get back to you as soon as possible.</p>

                            @if($errors->any())
                                <div style="margin-bottom:14px;background:rgba(248,113,113,.08);border:1px solid rgba(248,113,113,.35);border-radius:8px;padding:10px 12px;color:#fecaca;font-size:12px;">
                                    {{ $errors->first() }}
                                </div>
                            @endif

                            <form method="post" action="{{ url('/contact') }}" class="light_bg comment-form">
                                @csrf
                                <div class="contact-grid">
                                    <div class="contact-field">
                                        <label for="contact-author">Full Name *</label>
                                        <input id="contact-author" placeholder="John Doe" name="author" type="text" value="{{ old('author') }}" required>
                                    </div>
                                    <div class="contact-field">
                                        <label for="contact-phone">Phone Number *</label>
                                        <input id="contact-phone" placeholder="+20 100 000 0000" name="phone" type="text" value="{{ old('phone') }}" required>
                                    </div>
                                    <div class="contact-field">
                                        <label for="contact-email">Email Address *</label>
                                        <input id="contact-email" placeholder="you@example.com" name="email" type="email" value="{{ old('email') }}" required>
                                    </div>
                                    <div class="contact-field">
                                        <label for="contact-subject">Subject</label>
                                        <input id="contact-subject" placeholder="How can we help?" name="subject" type="text" value="{{ old('subject') }}">
                                    </div>
                                    <div class="contact-field full">
                                        <label for="contact-message">Message *</label>
                                        <textarea id="contact-message" placeholder="Tell us how we can help" name="comment" required>{{ old('comment') }}</textarea>
                                    </div>
                                    <div class="full">
                                        <button name="submit" type="submit" class="contact-submit btn-1 theme-bg">Send Message</button>
                                    </div>
                                </div>
                            </form>
                        </div>
                    </div>

                    <div class="col-md-5 col-sm-12">
                        <div class="widget-contact contact-card social-contact">
                            <h4 class="widget-title2">Direct Contact</h4>
                            <p class="contact-subtitle">TKT House operates techno events and digital ticketing support daily. Contact us for group bookings, VIP packages, sponsorship opportunities, and event logistics.</p>
                            <ul class="kf_contact_meta contact-info-list">
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
