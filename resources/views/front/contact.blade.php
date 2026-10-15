@extends('front.layout.app')

@section('title', 'Contact')

@section('content')
    <x-tkt.page-header title="Contact TKT House" sub="Reach our team for bookings, partnerships, and support." />

    <section class="tkt-section tkt-section--tight">
        <div class="tkt-container">
            <div class="tkt-contact">
                <div class="tkt-card">
                    <div class="tkt-card__body">
                        <h2 class="tkt-h3">Send us a message</h2>
                        <p class="tkt-text-muted tkt-text-sm" style="margin:6px 0 20px">Fill in your details and our team will get back to you as soon as possible.</p>
                        @if($errors->any())
                            <div class="tkt-alert tkt-alert--danger" role="alert" style="margin-bottom:16px">{{ $errors->first() }}</div>
                        @endif

                        <form method="post" action="{{ url('/contact') }}" data-tkt-submit>
                            @csrf
                            <div class="tkt-form-grid tkt-form-grid--2">
                                <div class="tkt-field">
                                    <label class="tkt-label" for="contact-author">Full name *</label>
                                    <input class="tkt-input @error('author') is-invalid @enderror" id="contact-author" placeholder="John Doe" name="author" type="text" value="{{ old('author') }}" required>
                                    @error('author')<div class="tkt-field__error">{{ $message }}</div>@enderror
                                </div>
                                <div class="tkt-field">
                                    <label class="tkt-label" for="contact-phone">Phone number *</label>
                                    <input class="tkt-input @error('phone') is-invalid @enderror" id="contact-phone" placeholder="100 000 0000" name="phone" type="tel" data-phone-intl value="{{ old('phone') }}" required>
                                    @error('phone')<div class="tkt-field__error">{{ $message }}</div>@enderror
                                </div>
                                <div class="tkt-field">
                                    <label class="tkt-label" for="contact-email">Email address *</label>
                                    <input class="tkt-input @error('email') is-invalid @enderror" id="contact-email" placeholder="you@example.com" name="email" type="email" value="{{ old('email') }}" required>
                                    @error('email')<div class="tkt-field__error">{{ $message }}</div>@enderror
                                </div>
                                <div class="tkt-field">
                                    <label class="tkt-label" for="contact-subject">Subject</label>
                                    <input class="tkt-input @error('subject') is-invalid @enderror" id="contact-subject" placeholder="How can we help?" name="subject" type="text" value="{{ old('subject') }}">
                                    @error('subject')<div class="tkt-field__error">{{ $message }}</div>@enderror
                                </div>
                                <div class="tkt-field is-full">
                                    <label class="tkt-label" for="contact-message">Message *</label>
                                    <textarea class="tkt-textarea @error('comment') is-invalid @enderror" id="contact-message" placeholder="Tell us how we can help" name="comment" required>{{ old('comment') }}</textarea>
                                    @error('comment')<div class="tkt-field__error">{{ $message }}</div>@enderror
                                </div>
                            </div>
                            <button name="submit" type="submit" class="tkt-btn tkt-btn--primary tkt-btn--lg tkt-btn--block">Send message</button>
                        </form>
                    </div>
                </div>

                <div class="tkt-card">
                    <div class="tkt-card__body">
                        <h2 class="tkt-h3">Direct contact</h2>
                        <p class="tkt-text-muted tkt-text-sm" style="margin:6px 0 8px">TKT House operates techno events and digital ticketing support daily. Contact us for group bookings, VIP packages, sponsorship opportunities, and event logistics.</p>
                        <ul class="tkt-contact-list">
                            <li><span class="tkt-contact-list__icon"><i class="fa fa-phone" aria-hidden="true"></i></span><a href="tel:+201005558899">+20 100 555 8899</a></li>
                            <li><span class="tkt-contact-list__icon"><i class="fa fa-envelope" aria-hidden="true"></i></span><a href="mailto:support@tkthouse.com">support@tkthouse.com</a></li>
                            <li><span class="tkt-contact-list__icon"><i class="fa fa-map-marker" aria-hidden="true"></i></span><span>New Cairo, Egypt</span></li>
                        </ul>
                    </div>
                </div>
            </div>
        </div>
    </section>
@endsection
