        <!-- Auth Modal — Premium Dark Gold -->
        <style>
        @import url('https://fonts.googleapis.com/css2?family=Syne:wght@600;700;800&family=DM+Sans:wght@300;400;500&display=swap');

        /* ── Override Bootstrap modal backdrop ── */
        #login-register1.modal { z-index: 99999; }
        #login-register1 .modal-dialog {
            max-width: 860px;
            width: 95vw;
            margin: 5vh auto;
        }
        #login-register1 .modal-content {
            background: #0a0a0e;
            border: 1px solid rgba(255,255,255,0.07);
            border-radius: 16px;
            box-shadow: 0 32px 80px rgba(0,0,0,0.7), 0 0 0 1px rgba(245,184,0,0.06);
            overflow: hidden;
            padding: 0;
            position: relative;
        }

        /* ── Close button ── */
        #login-register1 .auth-close {
            position: absolute;
            top: 16px; right: 16px;
            width: 32px; height: 32px;
            border-radius: 50%;
            background: rgba(255,255,255,0.05);
            border: 1px solid rgba(255,255,255,0.08);
            color: #6b6b7e;
            font-size: 16px;
            line-height: 1;
            cursor: pointer;
            display: flex; align-items: center; justify-content: center;
            transition: background 0.2s, color 0.2s;
            z-index: 10;
        }
        #login-register1 .auth-close:hover { background: rgba(232,68,90,0.15); color: #e8445a; border-color: rgba(232,68,90,0.3); }

        /* ── Tab switcher ── */
        .auth-tabs {
            display: flex;
            border-bottom: 1px solid rgba(255,255,255,0.07);
            background: #060608;
        }
        .auth-tab {
            flex: 1;
            padding: 18px 24px;
            font-family: 'Syne', sans-serif;
            font-size: 12px; font-weight: 700;
            letter-spacing: 2px; text-transform: uppercase;
            color: #6b6b7e;
            cursor: pointer;
            border: none; background: transparent;
            border-bottom: 2px solid transparent;
            margin-bottom: -1px;
            transition: color 0.2s, border-color 0.2s;
        }
        .auth-tab.active { color: #f5b800; border-bottom-color: #f5b800; }
        .auth-tab:hover:not(.active) { color: #e8e8ef; }

        /* ── Panels ── */
        .auth-panel { display: none; padding: 36px 40px 40px; }
        .auth-panel.active { display: block; animation: authFade 0.25s ease; }
        @keyframes authFade { from { opacity:0; transform:translateY(6px); } to { opacity:1; transform:translateY(0); } }

        /* ── Two-column layout (login only) ── */
        .auth-two-col {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 48px;
        }
        .auth-divider {
            position: relative;
            display: flex; align-items: center; justify-content: center;
        }
        .auth-divider::before {
            content: '';
            position: absolute;
            top: 0; bottom: 0; left: 50%;
            width: 1px;
            background: rgba(255,255,255,0.07);
        }
        .auth-divider-label {
            position: relative; z-index: 1;
            background: #0a0a0e;
            padding: 8px 12px;
            font-family: 'Syne', sans-serif;
            font-size: 11px; font-weight: 700;
            letter-spacing: 2px; text-transform: uppercase;
            color: #6b6b7e;
            border: 1px solid rgba(255,255,255,0.07);
            border-radius: 99px;
        }
        @media (max-width: 640px) {
            .auth-two-col { grid-template-columns: 1fr; gap: 32px; }
            .auth-divider { display: none; }
            .auth-panel { padding: 28px 22px 32px; }
        }

        /* ── Section heading ── */
        .auth-heading { margin-bottom: 24px; }
        .auth-heading h3 {
            font-family: 'Syne', sans-serif;
            font-size: 20px; font-weight: 800;
            color: #fff; margin: 0 0 6px;
            letter-spacing: -0.3px;
        }
        .auth-heading p {
            font-family: 'DM Sans', sans-serif;
            font-size: 13px; color: #6b6b7e;
            margin: 0;
        }

        /* ── Form fields ── */
        .auth-field { margin-bottom: 14px; }
        .auth-field label {
            display: block;
            font-family: 'DM Sans', sans-serif;
            font-size: 10px; font-weight: 500;
            letter-spacing: 1px; text-transform: uppercase;
            color: #6b6b7e; margin-bottom: 6px;
        }
        .auth-field input {
            width: 100%;
            background: #16161d;
            border: 1px solid rgba(255,255,255,0.07);
            border-radius: 8px;
            color: #e8e8ef;
            font-family: 'DM Sans', sans-serif;
            font-size: 14px;
            padding: 11px 14px;
            outline: none;
            transition: border-color 0.2s, box-shadow 0.2s;
            box-sizing: border-box;
        }
        .auth-field input::placeholder { color: #3a3a4a; }
        .auth-field input:focus {
            border-color: rgba(245,184,0,0.5);
            box-shadow: 0 0 0 3px rgba(245,184,0,0.08);
        }

        /* ── Submit button ── */
        .auth-submit {
            width: 100%;
            background: #f5b800;
            color: #000;
            font-family: 'Syne', sans-serif;
            font-size: 13px; font-weight: 800;
            letter-spacing: 1px; text-transform: uppercase;
            border: none; border-radius: 8px;
            padding: 13px 24px;
            cursor: pointer;
            margin-top: 6px;
            transition: background 0.2s, transform 0.1s;
        }
        .auth-submit:hover { background: #ffc820; }
        .auth-submit:active { transform: scale(0.99); }

        .auth-meta-row {
            display: flex;
            justify-content: space-between;
            align-items: center;
            gap: 12px;
            margin: -6px 0 12px;
        }
        .auth-remember {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            font-family: 'DM Sans', sans-serif;
            font-size: 13px;
            line-height: 1;
            color: #e8e8ef;
            cursor: pointer;
            margin: 0;
        }
        .auth-remember span {
            position: relative;
            top: 1px;
        }
        .auth-remember input {
            width: 18px;
            height: 18px;
            appearance: none;
            -webkit-appearance: none;
            border: 1.5px solid rgba(245,184,0,0.55);
            border-radius: 4px;
            background: #0b0b11;
            display: inline-grid;
            place-content: center;
            cursor: pointer;
            transition: border-color 0.2s, background-color 0.2s, box-shadow 0.2s;
        }
        .auth-remember input:hover { border-color: #f5b800; }
        .auth-remember input:focus-visible {
            outline: none;
            box-shadow: 0 0 0 3px rgba(245,184,0,0.2);
        }
        .auth-remember input:checked {
            background-color: #f5b800;
            border-color: #f5b800;
            background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 12 10'%3E%3Cpath fill='%23060608' d='M4.26 9.18.72 5.64l1.06-1.06 2.48 2.47L10.22 1.1l1.06 1.06-7.02 7.02z'/%3E%3C/svg%3E");
            background-repeat: no-repeat;
            background-position: center;
            background-size: 11px 9px;
        }
        .auth-legal {
            font-family: 'DM Sans', sans-serif;
            font-size: 12px;
            line-height: 1.5;
            color: #9a9ab2;
            margin: 0 0 16px;
        }
        .auth-legal a {
            color: #f5b800;
            text-decoration: none;
            font-weight: 500;
        }
        .auth-legal a:hover { text-decoration: underline; }
        .auth-legal--register { margin-top: 14px; }

        /* ── Gold accent line at top ── */
        .auth-modal-accent {
            height: 3px;
            background: linear-gradient(90deg, transparent 0%, #f5b800 40%, #c99300 70%, transparent 100%);
        }
        </style>

        <div id="login-register1" class="modal fade" role="dialog">
            <div class="modal-dialog">
                <div class="modal-content">
                    <div class="auth-modal-accent"></div>
                    <button type="button" class="auth-close" data-dismiss="modal" aria-label="Close">✕</button>

                    {{-- Tabs --}}
                    <div class="auth-tabs">
                        <button class="auth-tab active" data-auth-tab="login">Sign In</button>
                        <button class="auth-tab" data-auth-tab="register">Create Account</button>
                    </div>

                    @if(session('success'))
                        <div style="margin:12px 22px 0;background:rgba(34,197,94,.08);border:1px solid rgba(34,197,94,.25);border-radius:8px;padding:10px 12px;color:#86efac;font-size:12px;">{{ session('success') }}</div>
                    @endif

                    {{-- Sign In Panel --}}
                    <div class="auth-panel active" id="auth-panel-login">
                        <div class="auth-heading">
                            <h3>Welcome back</h3>
                            <p>Sign in to your account to continue</p>
                            <div data-auth-message="login" style="display:none;margin-top:10px;border-radius:8px;padding:10px 12px;font-size:12px;"></div>
                        </div>
                        <form method="POST" action="{{ route('front.customer.login.store') }}" data-auth-ajax-form data-auth-type="login">
                            @csrf
                            <input type="hidden" name="redirect_to" value="{{ request('redirect') }}" data-redirect-target>
                            <div class="auth-field">
                                <label>Email or Username</label>
                                <input type="text" name="login" placeholder="you@example.com" value="{{ old('login') }}" required>
                            </div>
                            <div class="auth-field">
                                <label>Password</label>
                                <input type="password" name="password" placeholder="••••••••" required>
                            </div>
                            <div class="auth-meta-row">
                                <label class="auth-remember">
                                    <input type="checkbox" name="remember" value="1" {{ old('remember') ? 'checked' : '' }}>
                                    <span>Remember Me</span>
                                </label>
                                <a href="#" data-auth-switch="forgot" style="color:#f5b800;font-size:12px;text-decoration:none;">Forgot password?</a>
                            </div>
                            <p class="auth-legal">
                                By continuing, you agree to our
                                <a href="{{ route('front.terms') }}" target="_blank" rel="noopener">Terms and Conditions</a>
                                and understand how your information is used as described in our
                                <a href="{{ route('front.privacy') }}" target="_blank" rel="noopener">Privacy Policy</a>.
                            </p>
                            @error('login')
                                <div style="margin-top:6px;color:#f0849a;font-size:12px;">{{ $message }}</div>
                            @enderror

                            <button class="auth-submit" type="submit">Sign In →</button>
                        </form>
                        <p style="text-align:center;margin-top:18px;font-family:'DM Sans',sans-serif;font-size:13px;color:#6b6b7e;">
                            Don't have an account?
                            <a href="#" data-auth-switch="register" style="color:#f5b800;text-decoration:none;font-weight:500;">Create one</a>
                        </p>
                    </div>

                    {{-- Register Panel --}}
                    <div class="auth-panel" id="auth-panel-register">
                        <div class="auth-heading">
                            <h3>Create your account</h3>
                            <p>Join us and start booking tickets</p>
                            <div data-auth-message="register" style="display:none;margin-top:10px;border-radius:8px;padding:10px 12px;font-size:12px;"></div>
                        </div>
                        <form method="POST" action="{{ route('front.customer.register.request-otp') }}" data-auth-ajax-form data-auth-type="register" data-otp-request-url="{{ route('front.customer.register.request-otp') }}" data-otp-verify-url="{{ route('front.customer.register.verify-otp') }}">
                            @csrf
                            <input type="hidden" name="redirect_to" value="{{ request('redirect') }}" data-redirect-target>
                            <div style="display:grid;grid-template-columns:1fr 1fr;gap:12px;">
                                <div class="auth-field" style="margin-bottom:0">
                                    <label>Full Name</label>
                                    <input type="text" name="name" placeholder="John Doe" value="{{ old('name') }}" required>
                                </div>
                                <div class="auth-field" style="margin-bottom:0">
                                    <label>Email Address</label>
                                    <input type="email" name="email" placeholder="you@example.com" value="{{ old('email') }}" required>
                                </div>
                            </div>
                            <div class="auth-field" style="margin-top:14px;margin-bottom:0">
                                <label>Phone Number</label>
                                <input type="tel" name="phone" data-phone-intl placeholder="+1 234 567 890" value="{{ old('phone') }}" required>
                            </div>
                            <div style="display:grid;grid-template-columns:1fr 1fr;gap:12px;margin-top:14px;">
                                <div class="auth-field" style="margin-bottom:0">
                                    <label>Password</label>
                                    <input type="password" name="password" placeholder="••••••••" required>
                                </div>
                                <div class="auth-field" style="margin-bottom:0">
                                    <label>Confirm Password</label>
                                    <input type="password" name="password_confirmation" placeholder="••••••••" required>
                                </div>
                            </div>

                            <div class="auth-field" style="margin-top:14px;display:none;" data-otp-field-wrap>
                                <label>Email OTP</label>
                                <input type="text" name="otp" placeholder="Enter 6-digit code" inputmode="numeric" pattern="[0-9]{6}" maxlength="6">
                                <div style="margin-top:8px;font-size:12px;color:#9a9ab2;">We sent a verification code to your email. Your account will be created only after OTP verification.</div>
                            </div>

                            @if ($errors->has('name') || $errors->has('email') || $errors->has('phone') || $errors->has('password'))
                                <div style="margin-top:12px;background:rgba(232,68,90,0.08);border:1px solid rgba(232,68,90,0.3);border-radius:8px;padding:10px 12px;color:#f0849a;font-size:12px;">
                                    @error('name')<div>{{ $message }}</div>@enderror
                                    @error('email')<div>{{ $message }}</div>@enderror
                                    @error('phone')<div>{{ $message }}</div>@enderror
                                    @error('password')<div>{{ $message }}</div>@enderror
                                </div>
                            @endif
                            <p class="auth-legal auth-legal--register">
                                By creating an account, you agree to our
                                <a href="{{ route('front.terms') }}" target="_blank" rel="noopener">Terms and Conditions</a>
                                and confirm that you have read our
                                <a href="{{ route('front.privacy') }}" target="_blank" rel="noopener">Privacy Policy</a>.
                            </p>
                            <button class="auth-submit" type="submit">Create Account →</button>
                        </form>
                        <p style="text-align:center;margin-top:18px;font-family:'DM Sans',sans-serif;font-size:13px;color:#6b6b7e;">
                            Already have an account?
                            <a href="#" data-auth-switch="login" style="color:#f5b800;text-decoration:none;font-weight:500;">Sign in</a>
                        </p>
                    </div>

                    {{-- Forgot Password Panel --}}
                    <div class="auth-panel" id="auth-panel-forgot">
                        <div class="auth-heading">
                            <h3>Reset your password</h3>
                            <p>Enter your account email and we'll send a reset link.</p>
                            <div data-auth-message="forgot" style="display:none;margin-top:10px;border-radius:8px;padding:10px 12px;font-size:12px;"></div>
                        </div>
                        <form method="POST" action="{{ route('password.email') }}" data-auth-ajax-form data-auth-type="forgot">
                            @csrf
                            <div class="auth-field">
                                <label>Email Address</label>
                                <input type="email" name="email" placeholder="you@example.com" value="{{ old('email') }}" required>
                            </div>
                            @error('email')
                                <div style="margin-top:6px;color:#f0849a;font-size:12px;">{{ $message }}</div>
                            @enderror

                            <button class="auth-submit" type="submit">Send Reset Link</button>
                        </form>
                        <p style="text-align:center;margin-top:18px;font-family:'DM Sans',sans-serif;font-size:13px;color:#6b6b7e;">
                            Remembered your password?
                            <a href="#" data-auth-switch="login" style="color:#f5b800;text-decoration:none;font-weight:500;">Sign in</a>
                        </p>
                    </div>

                </div>
            </div>
        </div>

        <script>
        (function(){
            // Tab switching
            document.querySelectorAll('[data-auth-tab]').forEach(function(tab){
                tab.addEventListener('click', function(){
                    var target = tab.dataset.authTab;
                    document.querySelectorAll('.auth-tab').forEach(function(t){ t.classList.remove('active'); });
                    document.querySelectorAll('.auth-panel').forEach(function(p){ p.classList.remove('active'); });
                    tab.classList.add('active');
                    document.getElementById('auth-panel-' + target).classList.add('active');
                });
            });
            // Inline "switch" links
            document.querySelectorAll('[data-auth-switch]').forEach(function(link){
                link.addEventListener('click', function(e){
                    e.preventDefault();
                    var target = link.dataset.authSwitch;
                    document.querySelectorAll('[data-auth-tab]').forEach(function(t){
                        t.classList.toggle('active', t.dataset.authTab === target);
                    });
                    document.querySelectorAll('.auth-panel').forEach(function(p){ p.classList.remove('active'); });
                    document.getElementById('auth-panel-' + target).classList.add('active');
                });
            });
        })();
        </script>
