@extends('front.layout.app')

@section('title', 'View your tickets')

@section('content')
<section class="tkt-container tkt-auth-page">
    <div class="tkt-auth-card">
        <div class="tkt-auth-card__body">
            <div class="tkt-auth__heading">
                <h1 class="tkt-h2">View your tickets</h1>
                <p>Confirm your email and your digital tickets open right away — no password and no account to set up.</p>
            </div>

            <div id="ta-email-step">
                <div class="tkt-field">
                    <label class="tkt-label" for="ta-email">Email address</label>
                    <input class="tkt-input" id="ta-email" type="email" inputmode="email" autocomplete="email" placeholder="you@example.com" autofocus required>
                    <div class="tkt-field__hint">Use the email your tickets were sent to.</div>
                    <div class="tkt-field__error" id="ta-email-error" hidden></div>
                </div>
                <button type="button" class="tkt-btn tkt-btn--primary tkt-btn--lg tkt-btn--block" id="ta-send">Email me a code</button>
            </div>

            <div id="ta-code-step" hidden>
                <x-tkt.otp-panel email="" :after="$target" :send-url="route('front.tickets.access.send')" id-suffix="access" />
                <button type="button" class="tkt-btn tkt-btn--ghost tkt-btn--block" style="margin-top:8px" id="ta-change">Use a different email</button>
            </div>
        </div>
        <div class="tkt-auth-card__foot">Prefer a password? <a href="{{ route('front.customer.login', ['redirect' => $target]) }}">Log in</a></div>
    </div>
</section>
@endsection

@push('scripts')
<script src="{{ asset('js/tkt-otp.js') }}?v={{ filemtime(public_path('js/tkt-otp.js')) }}"></script>
<script>
(function () {
    var email = document.getElementById('ta-email'), err = document.getElementById('ta-email-error'), btn = document.getElementById('ta-send');
    var panel = document.querySelector('[data-otp-panel]');
    function go() {
        var v = (email.value || '').trim();
        if (!/^\S+@\S+\.\S+$/.test(v)) { err.textContent = 'Enter a valid email address.'; err.hidden = false; return; }
        err.hidden = true;
        var otp = window.TktOtp.init(panel);
        otp.setEmail(v);
        btn.disabled = true;
        otp.send().then(function (res) {
            btn.disabled = false;
            if (res && res.ok && res.data && res.data.ok) {
                document.getElementById('ta-email-step').hidden = true;
                document.getElementById('ta-code-step').hidden = false;
                var c = panel.querySelector('[data-otp-code]'); if (c) c.focus();
            }
        });
    }
    btn.addEventListener('click', go);
    email.addEventListener('keydown', function (e) { if (e.key === 'Enter') { e.preventDefault(); go(); } });
    document.getElementById('ta-change').addEventListener('click', function () {
        document.getElementById('ta-code-step').hidden = true;
        document.getElementById('ta-email-step').hidden = false;
        email.focus();
    });
})();
</script>
@endpush
