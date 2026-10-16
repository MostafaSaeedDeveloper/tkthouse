@extends('front.account.layout')
@section('title', 'My account')
@section('heading', 'My account')

@section('portal')
<div class="tkt-portal__split">
    <div class="tkt-portal__main">
        {{-- Personal details --}}
        <section class="tkt-card tkt-pcard" aria-labelledby="detailsTitle">
            <h2 class="tkt-pcard__h" id="detailsTitle">Your details</h2>
            <form method="POST" action="{{ route('front.account.profile.update') }}" enctype="multipart/form-data" novalidate>
                @csrf
                @method('PUT')
                <div class="tkt-form-grid tkt-form-grid--2">
                    <div class="tkt-field is-full">
                        <label class="tkt-label" for="acc_name">Full name</label>
                        <input class="tkt-input {{ $errors->has('name') ? 'is-invalid' : '' }}" id="acc_name" name="name" type="text" value="{{ old('name', $user->name) }}" autocomplete="name" required>
                        @error('name')<div class="tkt-field__error">{{ $message }}</div>@enderror
                    </div>
                    <div class="tkt-field is-full">
                        <label class="tkt-label" for="acc_phone">Phone number <span class="tkt-text-muted" style="font-weight:500">(with country code)</span></label>
                        <input class="tkt-input {{ $errors->has('phone') ? 'is-invalid' : '' }}" id="acc_phone" name="phone" type="tel" data-phone-intl value="{{ old('phone', $user->phone) }}" autocomplete="tel" placeholder="100 000 0000">
                        @error('phone')<div class="tkt-field__error">{{ $message }}</div>@enderror
                    </div>
                </div>
                <button class="tkt-btn tkt-btn--primary" type="submit" style="margin-top:16px">Save changes</button>
            </form>
        </section>

        {{-- Email: ownership of orders and tickets follows it, so it changes only after a code sent to the NEW address --}}
        <section class="tkt-card tkt-pcard" aria-labelledby="emailTitle" id="emailCard"
                 data-request-url="{{ route('front.account.email.request') }}" data-confirm-url="{{ route('front.account.email.confirm') }}"
                 data-needs-password="{{ $passwordless ? 0 : 1 }}" data-fresh-otp="{{ $freshOtp ? 1 : 0 }}">
            <h2 class="tkt-pcard__h" id="emailTitle">Email</h2>
            <div class="tkt-pcard__row">
                <div class="tkt-wrap"><b data-current-email>{{ $user->email }}</b></div>
                @if($user->email_verified_at)
                    <x-tkt.badge variant="success">Verified</x-tkt.badge>
                @else
                    <x-tkt.badge variant="warning">Not verified</x-tkt.badge>
                @endif
            </div>
            <p class="tkt-text-muted tkt-text-sm" style="margin-top:6px">Your orders and tickets are tied to this address. To change it we send a code to the new one — nothing changes until you enter it.</p>

            <button type="button" class="tkt-btn tkt-btn--outline" style="margin-top:12px" data-email-open aria-expanded="false" aria-controls="emailForm">Change email</button>

            <div id="emailForm" hidden style="margin-top:16px">
                @if($passwordless && ! $freshOtp)
                    <div class="tkt-alert tkt-alert--info"><div>For your security, sign out and sign in again with an email code, then come back here to change your email.</div></div>
                @else
                    <div data-step="request">
                        <div class="tkt-field">
                            <label class="tkt-label" for="new_email">New email address</label>
                            <input class="tkt-input" id="new_email" type="email" autocomplete="email" inputmode="email">
                        </div>
                        @unless($passwordless)
                            <div class="tkt-field" style="margin-top:12px">
                                <label class="tkt-label" for="cur_password">Current password</label>
                                <input class="tkt-input" id="cur_password" type="password" autocomplete="current-password">
                            </div>
                        @endunless
                        <button type="button" class="tkt-btn tkt-btn--dark" style="margin-top:14px" data-email-send>Send code to new email</button>
                    </div>
                    <div data-step="confirm" hidden>
                        <div class="tkt-field">
                            <label class="tkt-label" for="email_code">6-digit code</label>
                            <input class="tkt-input tkt-otp__input" id="email_code" type="text" inputmode="numeric" autocomplete="one-time-code" maxlength="6" placeholder="······">
                        </div>
                        <button type="button" class="tkt-btn tkt-btn--primary" style="margin-top:14px" data-email-confirm>Confirm new email</button>
                        <button type="button" class="tkt-btn tkt-btn--ghost" style="margin-top:14px" data-email-back>Use a different address</button>
                    </div>
                    <div class="tkt-field__error" data-email-error hidden role="alert"></div>
                    <div class="tkt-field__hint" data-email-info hidden role="status"></div>
                @endif
            </div>
        </section>
    </div>

    <aside class="tkt-portal__side">
        <section class="tkt-card tkt-pcard">
            <h2 class="tkt-pcard__h">Sign-in</h2>
            @if($passwordless)
                <p class="tkt-text-sm">You sign in with a code we email you — no password needed.</p>
                <a class="tkt-btn tkt-btn--outline" style="margin-top:12px" href="{{ route('front.account.set-password') }}">Set a password (optional)</a>
                @unless($freshOtp)<p class="tkt-text-muted tkt-text-sm" style="margin-top:8px">Setting a password needs a recent sign-in with an email code.</p>@endunless
            @else
                <p class="tkt-text-sm">You sign in with your email and password. You can also request an email code at any time.</p>
            @endif
        </section>

        <section class="tkt-card tkt-pcard">
            <h2 class="tkt-pcard__h">Overview</h2>
            <dl class="tkt-pbreak tkt-pbreak--meta">
                <div><dt>Orders</dt><dd><a class="tkt-link" href="{{ route('front.account.orders') }}">{{ $orderCount }}</a></dd></div>
                <div><dt>Tickets</dt><dd><a class="tkt-link" href="{{ route('front.account.tickets', ['when' => 'all']) }}">{{ $ticketCount }}</a></dd></div>
                <div><dt>Member since</dt><dd>{{ $user->created_at?->format('M Y') }}</dd></div>
            </dl>
        </section>

        <form method="POST" action="{{ route('front.customer.logout') }}">
            @csrf
            <button type="submit" class="tkt-btn tkt-btn--outline tkt-btn--block">Log out</button>
        </form>
    </aside>
</div>
@endsection

@push('scripts')
<script>
(function () {
    var card = document.getElementById('emailCard');
    if (!card) return;
    var $ = function (s) { return card.querySelector(s); };
    var form = $('#emailForm'), open = $('[data-email-open]');
    var err = $('[data-email-error]'), info = $('[data-email-info]');
    var csrf = function () { return (document.querySelector('meta[name="csrf-token"]') || {}).content || ''; };
    var show = function (el, t) { if (!el) return; el.textContent = t || ''; el.hidden = !t; };
    var steps = { request: $('[data-step="request"]'), confirm: $('[data-step="confirm"]') };
    var go = function (n) { Object.keys(steps).forEach(function (k) { if (steps[k]) steps[k].hidden = k !== n; }); show(err, ''); };

    open.addEventListener('click', function () {
        var on = form.hidden;
        form.hidden = !on;
        open.setAttribute('aria-expanded', on ? 'true' : 'false');
        if (on) { var f = $('#new_email'); if (f) f.focus(); }
    });

    function post(url, data) {
        return fetch(url, { method: 'POST', credentials: 'same-origin', headers: { 'Accept': 'application/json', 'X-CSRF-TOKEN': csrf(), 'Content-Type': 'application/json' }, body: JSON.stringify(data) })
            .then(function (r) { return r.json().catch(function () { return {}; }).then(function (d) { return { ok: r.ok, status: r.status, d: d }; }); });
    }

    var send = $('[data-email-send]');
    if (send) send.addEventListener('click', function () {
        show(err, ''); show(info, '');
        var body = { email: ($('#new_email').value || '').trim() };
        if (card.dataset.needsPassword === '1') body.current_password = $('#cur_password').value;
        if (!body.email) { show(err, 'Enter the new email address.'); return; }
        send.disabled = true;
        post(card.dataset.requestUrl, body).then(function (r) {
            send.disabled = false;
            if (!r.ok) { show(err, (r.d.message || (r.d.errors && Object.values(r.d.errors)[0][0]) || 'Could not send the code.')); return; }
            go('confirm'); show(info, r.d.message); var c = $('#email_code'); if (c) c.focus();
        });
    });

    var confirm = $('[data-email-confirm]');
    if (confirm) confirm.addEventListener('click', function () {
        show(err, '');
        confirm.disabled = true;
        post(card.dataset.confirmUrl, { code: ($('#email_code').value || '').trim() }).then(function (r) {
            confirm.disabled = false;
            if (!r.ok) { show(err, r.d.message || 'That code is not valid.'); return; }
            window.location.reload();
        });
    });

    var back = $('[data-email-back]');
    if (back) back.addEventListener('click', function () { go('request'); show(info, ''); });
})();
</script>
@endpush
