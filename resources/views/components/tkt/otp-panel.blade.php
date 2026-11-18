{{-- Reusable e-mail code box. Behaviour lives in public/js/tkt-otp.js (data-otp-panel). --}}
@props(['email' => '', 'emailMasked' => null, 'order' => null, 'token' => null, 'after' => '', 'autosend' => false, 'idSuffix' => 'x'])
<div data-otp-panel
     data-send-url="{{ route('front.auth.otp.send') }}"
     data-verify-url="{{ route('front.auth.otp.verify') }}"
     data-email="{{ $email }}"
     @if($order) data-order="{{ $order }}" data-token="{{ $token }}" @endif
     data-after="{{ $after }}"
     @if($autosend) data-autosend @endif>
    <p class="tkt-text-sm tkt-text-muted" style="margin-bottom:12px">We emailed a 6-digit code to <strong data-otp-email>{{ $emailMasked ?? $email }}</strong>. It works once and expires in 10 minutes.</p>
    <div class="tkt-field">
        <label class="tkt-label" for="otp-code-{{ $idSuffix }}">Verification code</label>
        <input class="tkt-input tkt-otp__input" id="otp-code-{{ $idSuffix }}" type="text" inputmode="numeric" autocomplete="one-time-code" maxlength="6" placeholder="······" data-otp-code>
        <div class="tkt-field__error" data-otp-error hidden></div>
        <div class="tkt-field__hint" data-otp-info hidden></div>
    </div>
    <button type="button" class="tkt-btn tkt-btn--primary tkt-btn--lg tkt-btn--block" data-otp-verify>Verify and continue</button>
    <button type="button" class="tkt-btn tkt-btn--ghost tkt-btn--block" style="margin-top:8px" data-otp-resend>Send me a new code</button>
</div>
