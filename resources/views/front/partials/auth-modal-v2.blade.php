{{-- V2 auth modal. Keeps every data-auth-* hook used by partials/auth-scripts.blade.php. --}}
<div id="login-register1" class="modal fade tkt-modal tkt-auth-modal" role="dialog" aria-labelledby="authModalTitle">
    <div class="modal-dialog">
        <div class="modal-content tkt-auth">
            <button type="button" class="tkt-auth__close" data-dismiss="modal" aria-label="Close">&times;</button>

            <div class="auth-tabs tkt-auth__tabs" role="tablist">
                <button type="button" class="auth-tab active" data-auth-tab="login">Sign in</button>
                <button type="button" class="auth-tab" data-auth-tab="register">Create account</button>
            </div>

            {{-- Sign in --}}
            <div class="auth-panel active" id="auth-panel-login">
                <div class="tkt-auth__heading">
                    <h3 id="authModalTitle">Welcome back</h3>
                    <p>Sign in to your account to continue.</p>
                </div>
                <div class="tkt-alert" data-auth-message="login" style="display:none" role="alert"></div>
                <form method="POST" action="{{ route('front.customer.login.store') }}" data-auth-ajax-form data-auth-type="login" novalidate>
                    @csrf
                    <input type="hidden" name="redirect_to" value="{{ request('redirect') }}" data-redirect-target>
                    <div class="auth-field tkt-field">
                        <label class="tkt-label" for="am-login">Email or username</label>
                        <input class="tkt-input" id="am-login" type="text" name="login" placeholder="you@example.com" value="{{ old('login') }}" autocomplete="username" required>
                    </div>
                    <div class="auth-field tkt-field">
                        <label class="tkt-label" for="am-password">Password</label>
                        <input class="tkt-input" id="am-password" type="password" name="password" placeholder="••••••••" autocomplete="current-password" required>
                    </div>
                    <div class="tkt-auth__row">
                        <label class="tkt-check"><input type="checkbox" name="remember" value="1" {{ old('remember') ? 'checked' : '' }}><span>Remember me</span></label>
                        <a href="#" class="tkt-link" data-auth-switch="forgot">Forgot password?</a>
                    </div>
                    @error('login')<div class="tkt-field__error">{{ $message }}</div>@enderror
                    <p class="tkt-auth__legal">By continuing, you agree to our <a href="{{ route('front.terms') }}" target="_blank" rel="noopener">Terms</a> and <a href="{{ route('front.privacy') }}" target="_blank" rel="noopener">Privacy Policy</a>.</p>
                    <button class="tkt-btn tkt-btn--primary tkt-btn--lg tkt-btn--block auth-submit" type="submit">Sign in</button>
                </form>
                <p class="tkt-auth__switch">Don't have an account? <a href="#" data-auth-switch="register">Create one</a></p>
            </div>

            {{-- Register (+ email OTP step) --}}
            <div class="auth-panel" id="auth-panel-register">
                <div class="tkt-auth__heading">
                    <h3>Create your account</h3>
                    <p>Join us and start booking tickets.</p>
                </div>
                <div class="tkt-alert" data-auth-message="register" style="display:none" role="alert"></div>
                <form method="POST" action="{{ route('front.customer.register.request-otp') }}" data-auth-ajax-form data-auth-type="register" data-otp-request-url="{{ route('front.customer.register.request-otp') }}" data-otp-verify-url="{{ route('front.customer.register.verify-otp') }}" novalidate>
                    @csrf
                    <input type="hidden" name="redirect_to" value="{{ request('redirect') }}" data-redirect-target>
                    <div class="tkt-auth__grid">
                        <div class="auth-field tkt-field">
                            <label class="tkt-label" for="am-name">Full name</label>
                            <input class="tkt-input" id="am-name" type="text" name="name" placeholder="John Doe" value="{{ old('name') }}" autocomplete="name" required>
                        </div>
                        <div class="auth-field tkt-field">
                            <label class="tkt-label" for="am-email">Email address</label>
                            <input class="tkt-input" id="am-email" type="email" name="email" placeholder="you@example.com" value="{{ old('email') }}" autocomplete="email" required>
                        </div>
                    </div>
                    <div class="auth-field tkt-field">
                        <label class="tkt-label" for="am-phone">Phone number</label>
                        <input class="tkt-input" id="am-phone" type="tel" name="phone" data-phone-intl placeholder="100 000 0000" value="{{ old('phone') }}" required>
                    </div>
                    <div class="tkt-auth__grid">
                        <div class="auth-field tkt-field">
                            <label class="tkt-label" for="am-pass">Password</label>
                            <input class="tkt-input" id="am-pass" type="password" name="password" placeholder="At least 8 characters" autocomplete="new-password" required>
                        </div>
                        <div class="auth-field tkt-field">
                            <label class="tkt-label" for="am-pass2">Confirm password</label>
                            <input class="tkt-input" id="am-pass2" type="password" name="password_confirmation" placeholder="Repeat password" autocomplete="new-password" required>
                        </div>
                    </div>

                    <div class="auth-field tkt-field tkt-otp" style="display:none" data-otp-field-wrap>
                        <label class="tkt-label" for="am-otp">Email verification code</label>
                        <input class="tkt-input tkt-otp__input" id="am-otp" type="text" name="otp" placeholder="······" inputmode="numeric" autocomplete="one-time-code" pattern="[0-9]{6}" maxlength="6">
                        <div class="tkt-field__hint">We sent a 6-digit code to your email. Your account is created only after it is verified.</div>
                    </div>

                    @if ($errors->has('name') || $errors->has('email') || $errors->has('phone') || $errors->has('password'))
                        <div class="tkt-alert tkt-alert--danger" role="alert">
                            <div>
                                @error('name')<div>{{ $message }}</div>@enderror
                                @error('email')<div>{{ $message }}</div>@enderror
                                @error('phone')<div>{{ $message }}</div>@enderror
                                @error('password')<div>{{ $message }}</div>@enderror
                            </div>
                        </div>
                    @endif
                    <p class="tkt-auth__legal">By creating an account, you agree to our <a href="{{ route('front.terms') }}" target="_blank" rel="noopener">Terms</a> and confirm you have read our <a href="{{ route('front.privacy') }}" target="_blank" rel="noopener">Privacy Policy</a>.</p>
                    <button class="tkt-btn tkt-btn--primary tkt-btn--lg tkt-btn--block auth-submit" type="submit">Create account</button>
                </form>
                <p class="tkt-auth__switch">Already have an account? <a href="#" data-auth-switch="login">Sign in</a></p>
            </div>

            {{-- Forgot password --}}
            <div class="auth-panel" id="auth-panel-forgot">
                <div class="tkt-auth__heading">
                    <h3>Reset your password</h3>
                    <p>Enter your account email and we'll send a reset link.</p>
                </div>
                <div class="tkt-alert" data-auth-message="forgot" style="display:none" role="alert"></div>
                <form method="POST" action="{{ route('password.email') }}" data-auth-ajax-form data-auth-type="forgot" novalidate>
                    @csrf
                    <div class="auth-field tkt-field">
                        <label class="tkt-label" for="am-forgot">Email address</label>
                        <input class="tkt-input" id="am-forgot" type="email" name="email" placeholder="you@example.com" value="{{ old('email') }}" autocomplete="email" required>
                    </div>
                    @error('email')<div class="tkt-field__error">{{ $message }}</div>@enderror
                    <button class="tkt-btn tkt-btn--primary tkt-btn--lg tkt-btn--block auth-submit" type="submit">Send reset link</button>
                </form>
                <p class="tkt-auth__switch">Remembered your password? <a href="#" data-auth-switch="login">Sign in</a></p>
            </div>
        </div>
    </div>
</div>

<script>
(function () {
    function show(target) {
        document.querySelectorAll('[data-auth-tab]').forEach(function (t) { t.classList.toggle('active', t.dataset.authTab === target); });
        document.querySelectorAll('#login-register1 .auth-panel').forEach(function (p) { p.classList.remove('active'); });
        var panel = document.getElementById('auth-panel-' + target);
        if (panel) panel.classList.add('active');
    }
    document.querySelectorAll('#login-register1 [data-auth-tab]').forEach(function (tab) {
        tab.addEventListener('click', function () { show(tab.dataset.authTab); });
    });
    document.querySelectorAll('#login-register1 [data-auth-switch]').forEach(function (link) {
        link.addEventListener('click', function (e) { e.preventDefault(); show(link.dataset.authSwitch); });
    });
})();
</script>
