@extends('front.layout.app')

@section('title', 'Review & payment')

@php
    $money = fn ($n) => number_format((float) $n, 2).' EGP';
    $buyerName = trim(($buyer['first_name'] ?? '').' '.($buyer['last_name'] ?? ''));
    $selectedMethod = old('payment_method', $methods->count() === 1 ? $methods->first()->code : '');
    $payLabel = $requiresApproval ? 'Submit booking request' : 'Pay '.$money($quote->total);
@endphp

@section('content')
<div class="tkt-container tkt-checkout">
    <x-tkt.stepper :current="3" />

    <div class="tkt-checkout__head">
        <a class="tkt-back" style="color:var(--tkt-muted);margin:0 0 8px;display:inline-flex" href="{{ route('front.checkout') }}">
            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M19 12H5M11 6l-6 6 6 6"/></svg>
            Back to details
        </a>
        <h1 class="tkt-checkout__title">Review &amp; payment</h1>
    </div>

    <div class="tkt-checkout__layout">
        <form class="tkt-checkout__main" id="placeOrderForm" method="POST" action="{{ route('front.checkout.store') }}" data-checkout-form data-place-order>
            @csrf
            <input type="hidden" name="promo_code" id="promoHidden" value="{{ $promoCode }}">
            <input type="hidden" name="expected_total" id="expectedTotal" value="{{ number_format($quote->total, 2, '.', '') }}">

            @if($errors->any() && ! $errors->has('promo_code') && ! $errors->has('payment_method'))
                <div class="tkt-alert tkt-alert--danger" role="alert">{{ $errors->first() }}</div>
            @endif

            {{-- What you are buying --}}
            <section class="tkt-card tkt-section-card">
                <div style="display:flex;justify-content:space-between;gap:12px;align-items:center;flex-wrap:wrap;margin-bottom:12px">
                    <h2 class="tkt-section-card__title">Your order</h2>
                    <a class="tkt-link" href="{{ route('front.checkout') }}">Edit details</a>
                </div>
                <dl class="tkt-recap">
                    <div><dt>Buyer</dt><dd>{{ $buyerName }} · {{ $buyer['email'] }}@if(!empty($buyer['phone'])) · {{ $buyer['phone'] }}@endif</dd></div>
                    <div>
                        <dt>Attendees ({{ count($attendees) }})</dt>
                        <ul class="tkt-recap-people">
                            @php $n = 0; @endphp
                            @foreach($groups as $group)
                                @foreach($group['indexes'] as $i)
                                    <li><span>{{ $attendees[$i]['name'] }}</span><span>{{ $group['name'] }}</span></li>
                                @endforeach
                            @endforeach
                        </ul>
                    </div>
                </dl>
            </section>

            {{-- Promo --}}
            <section class="tkt-card tkt-section-card">
                <h2 class="tkt-section-card__title">Promo code</h2>
                <p class="tkt-section-card__sub">Have a code? The discount is checked and applied by our server.</p>
                <div class="tkt-promo">
                    <input class="tkt-input {{ $errors->has('promo_code') ? 'is-invalid' : '' }}" type="text" id="promoInput" value="{{ old('promo_code', $promoCode) }}" placeholder="ENTER CODE" autocomplete="off" aria-label="Promo code" maxlength="50" data-promo-url="{{ route('front.checkout.promo') }}">
                    <button type="button" class="tkt-btn tkt-btn--outline" id="promoApply">Apply</button>
                    <button type="button" class="tkt-btn tkt-btn--ghost" id="promoRemove" @if($promoCode === '') hidden @endif>Remove</button>
                </div>
                <div class="tkt-promo-msg {{ $errors->has('promo_code') ? 'is-error' : ($promoCode !== '' ? 'is-ok' : '') }}" id="promoMsg" role="status">
                    @if($errors->has('promo_code')){{ $errors->first('promo_code') }}@elseif($promoNotice){{ $promoNotice }}@elseif($promoCode !== ''){{ "Promo code {$promoCode} applied." }}@endif
                </div>
            </section>

            {{-- Price breakdown (mobile; desktop shows it in the sticky sidebar) --}}
            <div class="tkt-mobile-summary">@include('front.checkout._summary', ['event' => $event, 'quote' => $quote])</div>

            {{-- Payment --}}
            <section class="tkt-card tkt-section-card">
                <h2 class="tkt-section-card__title">Payment method</h2>
                @if($requiresApproval)
                    <div class="tkt-alert tkt-alert--info" style="margin-top:12px">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><circle cx="12" cy="12" r="10"/><path d="M12 16v-4M12 8h.01"/></svg>
                        <div>This event confirms bookings manually. Submit your request now — if it is approved we email you a secure link to pay. You are not charged today.</div>
                    </div>
                @else
                    <p class="tkt-section-card__sub">You'll complete payment on the provider's secure page. We never see or store your card details.</p>
                    @if($errors->has('payment_method'))
                        <div class="tkt-alert tkt-alert--danger" role="alert" style="margin-bottom:12px">{{ $errors->first('payment_method') }}</div>
                    @endif
                    <div class="tkt-methods" role="radiogroup" aria-label="Payment method">
                        @forelse($methods as $method)
                            <label class="tkt-method">
                                <input type="radio" name="payment_method" value="{{ $method->code }}" @checked($selectedMethod === $method->code) required>
                                <span class="tkt-method__radio" aria-hidden="true"></span>
                                <span class="tkt-method__icon" aria-hidden="true">@if($method->checkout_icon_url)<img src="{{ $method->checkout_icon_url }}" alt="">@else 💳 @endif</span>
                                <span class="tkt-method__body">
                                    <span class="tkt-method__name" style="display:block">{{ $method->checkout_label ?: $method->name }}</span>
                                    <span class="tkt-method__desc" style="display:block">{{ $method->checkout_description ?: 'Pay securely with '.$method->name }}</span>
                                </span>
                            </label>
                        @empty
                            <div class="tkt-alert tkt-alert--warning">No payment methods are available right now. Please try again later.</div>
                        @endforelse
                    </div>
                @endif
            </section>

            <p class="tkt-text-muted tkt-text-sm" style="padding:0 4px">By continuing you agree to our <a class="tkt-link" style="display:inline" href="{{ route('front.terms') }}" target="_blank" rel="noopener">Terms</a> and <a class="tkt-link" style="display:inline" href="{{ route('front.privacy') }}" target="_blank" rel="noopener">Privacy Policy</a>.</p>
        </form>

        <aside class="tkt-checkout__aside" aria-label="Order summary">
            @include('front.checkout._summary', ['event' => $event, 'quote' => $quote])
            <button type="submit" form="placeOrderForm" class="tkt-btn tkt-btn--primary tkt-btn--lg tkt-btn--block" style="margin-top:12px" data-loading-target data-pay-label @disabled(! $requiresApproval && $methods->isEmpty())>{{ $payLabel }}</button>
            <p class="tkt-ordersum__note"><svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" style="display:inline;vertical-align:-1px"><rect x="3" y="11" width="18" height="11" rx="2"/><path d="M7 11V7a5 5 0 0 1 10 0v4"/></svg> Secure payment · Final amount: {{ $money($quote->total) }}</p>
        </aside>
    </div>
</div>

<div class="tkt-bottombar" data-bottombar>
    <div class="tkt-bottombar__total"><small>Total to pay</small><b data-sum-total-bar>{{ $money($quote->total) }}</b></div>
    <button type="submit" form="placeOrderForm" class="tkt-btn tkt-btn--primary" data-loading-target data-pay-label @disabled(! $requiresApproval && $methods->isEmpty())>{{ $requiresApproval ? 'Submit request' : 'Pay now' }}</button>
</div>
@endsection

@push('scripts')
    <script src="{{ asset('js/tkt-checkout.js') }}?v={{ filemtime(public_path('js/tkt-checkout.js')) }}"></script>
@endpush
