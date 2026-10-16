@extends('front.layout.app')

@section('title', 'Create account')

@section('content')
<section class="tkt-container tkt-auth-page">
    <div class="tkt-auth-card">
        <div class="tkt-auth-card__body">
            <div class="tkt-auth__heading">
                <h1 class="tkt-h2">Create your account</h1>
                <p>Fill in your details to get started.</p>
            </div>

            <div class="tkt-alert" data-auth-message="register" style="display:none" role="alert"></div>

            {{-- Same AJAX + email OTP flow as the sign-up modal (the register endpoint answers JSON). --}}
            <form method="POST" action="{{ route('front.customer.register.store') }}" data-auth-ajax-form data-auth-type="register" data-otp-request-url="{{ route('front.customer.register.request-otp') }}" data-otp-verify-url="{{ route('front.customer.register.verify-otp') }}" novalidate>
                @csrf
                <input type="hidden" name="redirect_to" value="{{ request('redirect') }}" data-redirect-target>
                <div class="auth-field tkt-field">
                    <label class="tkt-label" for="reg-name">Full name</label>
                    <input class="tkt-input" id="reg-name" type="text" name="name" value="{{ old('name') }}" placeholder="John Doe" autocomplete="name" required>
                </div>
                <div class="auth-field tkt-field">
                    <label class="tkt-label" for="reg-email">Email address</label>
                    <input class="tkt-input" id="reg-email" type="email" name="email" value="{{ old('email') }}" placeholder="you@example.com" autocomplete="email" required>
                </div>
                <div class="auth-field tkt-field">
                    <label class="tkt-label" for="reg-phone">Phone number</label>
                    <input class="tkt-input" id="reg-phone" type="tel" name="phone" data-phone-intl value="{{ old('phone') }}" placeholder="01XXXXXXXXX" required>
                </div>
                <div class="tkt-auth__grid">
                    <div class="auth-field tkt-field">
                        <label class="tkt-label" for="reg-pass">Password</label>
                        <input class="tkt-input" id="reg-pass" type="password" name="password" placeholder="At least 8 characters" autocomplete="new-password" required>
                    </div>
                    <div class="auth-field tkt-field">
                        <label class="tkt-label" for="reg-pass2">Confirm password</label>
                        <input class="tkt-input" id="reg-pass2" type="password" name="password_confirmation" placeholder="Repeat password" autocomplete="new-password" required>
                    </div>
                </div>
                <div class="auth-field tkt-field tkt-otp" style="display:none" data-otp-field-wrap>
                    <label class="tkt-label" for="reg-otp">Email verification code</label>
                    <input class="tkt-input tkt-otp__input" id="reg-otp" type="text" name="otp" placeholder="······" inputmode="numeric" autocomplete="one-time-code" pattern="[0-9]{6}" maxlength="6">
                    <div class="tkt-field__hint">We sent a 6-digit code to your email. Your account is created only after it is verified.</div>
                </div>
                <p class="tkt-auth__legal">By creating an account, you agree to our <a href="{{ route('front.terms') }}" target="_blank" rel="noopener">Terms and Conditions</a> and confirm that you have read our <a href="{{ route('front.privacy') }}" target="_blank" rel="noopener">Privacy Policy</a>.</p>
                <button class="tkt-btn tkt-btn--primary tkt-btn--lg tkt-btn--block auth-submit" type="submit">Create account</button>
            </form>
        </div>
        <div class="tkt-auth-card__foot">Already have an account? <a href="{{ route('front.customer.login', ['redirect' => request('redirect')]) }}">Log in</a></div>
    </div>
</section>
@endsection
