@extends('front.layout.app')

@section('title', 'Checkout')

@php
    $money = fn ($n) => number_format((float) $n, 2).' EGP';
    $attendeeValue = fn (int $i, string $field) => old("attendees.$i.$field", data_get($attendees, "$i.$field", ''));
    $buyerName = old('full_name', $buyer['full_name'] ?? trim(($buyer['first_name'] ?? '').' '.($buyer['last_name'] ?? '')));
    $buyerEmail = $signedIn ? auth()->user()->email : old('email', $buyer['email'] ?? '');
    $buyerPhone = old('phone', $buyer['phone'] ?? '');
    $chosenMethod = old('payment_method', $selectedMethod ?: ($methods->count() === 1 ? $methods->first()->code : ''));
    $payLabel = $requiresApproval ? 'Submit booking request' : 'Pay now — '.$money($quote->total);
    $nameError = $errors->first('first_name') ?: $errors->first('last_name');
    $canPay = $requiresApproval || $methods->isNotEmpty();
@endphp

@section('content')
<div class="tkt-container tkt-checkout {{ $wizard ? 'tkt-wz' : '' }}" data-express @if($wizard) data-wz data-signed-in="{{ $signedIn ? 1 : 0 }}" data-preflight-email="{{ $preflightEmail }}" data-preflight-url="{{ route('front.checkout.preflight') }}" data-draft-url="{{ route('front.checkout.draft') }}" @endif>
    @unless($wizard)<x-tkt.stepper :current="2" />@endunless

    <div class="tkt-checkout__head">
        <a class="tkt-back" style="color:var(--tkt-muted);margin:0 0 8px;display:inline-flex" href="{{ route('front.events.show', $event) }}">
            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M19 12H5M11 6l-6 6 6 6"/></svg>
            Change tickets
        </a>
        <h1 class="tkt-checkout__title">Checkout</h1>
        @if($canCompare)
            <p class="tkt-text-sm tkt-text-muted" style="margin:6px 0 0" data-dev-compare>Dev compare:
                <a class="tkt-link" style="display:inline;{{ $wizard ? 'font-weight:800' : '' }}" href="{{ route('front.checkout', ['experience' => 'wizard']) }}">Wizard</a> ·
                <a class="tkt-link" style="display:inline;{{ $wizard ? '' : 'font-weight:800' }}" href="{{ route('front.checkout', ['experience' => 'one-page']) }}">One-page</a></p>
        @endif
    </div>

    <div class="tkt-checkout__layout">
        <form class="tkt-checkout__main" id="expressForm" method="POST" action="{{ route('front.checkout.store') }}" data-express-form data-verify-email="{{ $verifyEmail }}">
            @csrf
            <input type="hidden" name="promo_code" id="promoHidden" value="{{ $promoCode }}">
            <input type="hidden" name="expected_total" id="expectedTotal" value="{{ number_format($quote->total, 2, '.', '') }}">

            @if($wizard)
                <nav class="tkt-wz__progress" data-wz-progress aria-label="Checkout progress"></nav>
                {{-- Mobile: collapsible order summary that stays at the top for the whole journey --}}
                <div class="tkt-wz__msum" data-wz-msum>
                    <button type="button" class="tkt-wz__msum-btn" data-wz-msum-toggle aria-expanded="false">
                        <span>Order summary</span><b data-sum-total-bar>{{ $money($quote->total) }}</b>
                        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="m6 9 6 6 6-6"/></svg>
                    </button>
                    <div class="tkt-wz__msum-body" data-wz-msum-body hidden>@include('front.checkout._summary', ['event' => $event, 'quote' => $quote])</div>
                </div>
            @endif

            <div class="tkt-alert tkt-alert--danger" role="alert" id="formAlert" @if(! $errors->any() && ! session('error')) hidden @endif>
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><circle cx="12" cy="12" r="10"/><path d="M12 8v4M12 16h.01"/></svg>
                <div data-form-alert-text>@if($errors->any())Please check the highlighted fields and try again.@else{{ session('error') }}@endif</div>
            </div>

            {{-- Buyer --}}
            <section class="tkt-card tkt-section-card" aria-labelledby="buyerTitle" @if($wizard) data-wz-step="buyer" @endif>
                <h2 class="tkt-section-card__title" id="buyerTitle">Your contact details</h2>
                <p class="tkt-section-card__sub" data-signed-in-note>
                    @if($signedIn)Signed in as {{ auth()->user()->email }}. Your email is your account email unless you change and confirm another; other details apply to this order only.
                    @else We send your confirmation here. No account or password needed.@endif
                </p>
                <div class="tkt-form-grid tkt-form-grid--2">
                    <div class="tkt-field is-full">
                        <label class="tkt-label" for="buyer_name">Full name</label>
                        <input class="tkt-input {{ $nameError ? 'is-invalid' : '' }}" id="buyer_name" name="full_name" type="text" value="{{ $buyerName }}" autocomplete="name" placeholder="Jane Doe" required data-buyer-name>
                        @if($nameError)<div class="tkt-field__error">{{ $nameError }}</div>@endif
                    </div>
                    <div class="tkt-field">
                        <label class="tkt-label" for="buyer_email">Email address</label>
                        <input class="tkt-input {{ $errors->has('email') ? 'is-invalid' : '' }}" id="buyer_email" name="email" type="email" inputmode="email" value="{{ $buyerEmail }}" autocomplete="email" placeholder="jane@domain.com" required data-buyer-email @if($signedIn) readonly @endif>
                        @error('email')<div class="tkt-field__error">{{ $message }}</div>@enderror
                        <div data-contact-email @if(! $signedIn) hidden @endif data-account-email="{{ $signedIn ? auth()->user()->email : '' }}" data-send-url="{{ route('front.checkout.contact.send') }}" data-verify-url="{{ route('front.checkout.contact.verify') }}" style="margin-top:6px">
                                <button type="button" class="tkt-link" style="background:none;border:0;padding:0;font-weight:700;text-decoration:underline" data-contact-change>Change email</button>
                                <div data-contact-box hidden style="margin-top:8px">
                                    <button type="button" class="tkt-btn tkt-btn--ghost" data-contact-send>Send code to this email</button>
                                    <button type="button" class="tkt-link" style="background:none;border:0;padding:0;margin-left:8px" data-contact-cancel>Cancel</button>
                                    <div data-contact-code-row hidden style="margin-top:8px">
                                        <input class="tkt-input tkt-otp__input" type="text" inputmode="numeric" autocomplete="one-time-code" maxlength="6" placeholder="······" data-contact-code>
                                        <button type="button" class="tkt-btn tkt-btn--primary" style="margin-top:8px" data-contact-verify>Confirm email</button>
                                    </div>
                                    <div class="tkt-field__hint" data-contact-info hidden></div>
                                    <div class="tkt-field__error" data-contact-error hidden></div>
                                </div>
                        </div>
                    </div>
                    <div class="tkt-field">
                        <label class="tkt-label" for="buyer_phone">Phone number <span class="tkt-text-muted" style="font-weight:500">(optional)</span></label>
                        <input class="tkt-input {{ $errors->has('phone') ? 'is-invalid' : '' }}" id="buyer_phone" name="phone" type="tel" inputmode="tel" value="{{ $buyerPhone }}" autocomplete="tel" placeholder="100 000 0000" data-phone-intl data-buyer-phone>
                        @error('phone')<div class="tkt-field__error">{{ $message }}</div>@enderror
                    </div>
                    @unless($signedIn)
                        <div class="tkt-field is-full" data-guest-password>
                            <label class="tkt-label" for="buyer_password">Create a password <span class="tkt-text-muted" style="font-weight:500">(for your new account — not needed if you already have one)</span></label>
                            <input class="tkt-input {{ $errors->has('password') ? 'is-invalid' : '' }}" id="buyer_password" name="password" type="password" minlength="8" autocomplete="new-password" placeholder="At least 8 characters" data-buyer-password>
                            @error('password')<div class="tkt-field__error">{{ $message }}</div>@enderror
                        </div>
                    @endunless
                </div>
                @if($wizard)
                    {{-- Confirm the mailbox right here when this e-mail already has an account (no separate page) --}}
                    <div class="tkt-wz__otp" data-wz-otp hidden>
                        <h3 class="tkt-wz__otp-title">Confirm your email</h3>
                        <x-tkt.otp-panel email="" id-suffix="inline" />
                        <p class="tkt-text-muted tkt-text-sm" style="margin-top:12px">Typed it wrong? <button type="button" class="tkt-link" style="background:none;border:0;padding:0;font-weight:700;text-decoration:underline" data-otp-edit-email>Edit my email</button> — everything you entered stays.</p>
                    </div>
                @endif
            </section>

            {{-- Attendees --}}
            <section class="tkt-card tkt-section-card" aria-labelledby="attendeesTitle" id="attendeesSection" @if($wizard) data-wz-step="attendees" @endif>
                <h2 class="tkt-section-card__title" id="attendeesTitle">Who is attending?</h2>
                <p class="tkt-section-card__sub">{{ $wizard ? 'All fields are required. Entry is checked against these names.' : 'One form per ticket — all fields are required. Entry is checked against these names.' }}</p>

                @if($errors->has('attendees'))
                    <div class="tkt-alert tkt-alert--danger tkt-inline-alert" role="alert">{{ $errors->first('attendees') }}</div>
                @endif

                @foreach($groups as $group)
                    <div class="tkt-ticket-group">
                        <div class="tkt-ticket-group__head">
                            <span class="tkt-ticket-group__name">{{ $group['name'] }}</span>
                            <x-tkt.badge>{{ count($group['indexes']) }} {{ \Illuminate\Support\Str::plural('ticket', count($group['indexes'])) }} · {{ $money($group['price']) }} each</x-tkt.badge>
                            @if($group['is_couple'])<x-tkt.badge variant="warning">Couple pass</x-tkt.badge>@endif
                        </div>

                        @foreach($group['indexes'] as $pos => $i)
                            <div class="tkt-attendee" data-attendee="{{ $i }}" data-ticket-name="{{ $group['name'] }}" data-couple="{{ $group['is_couple'] ? 1 : 0 }}" data-person="{{ $pos + 1 }}" data-persons="{{ count($group['indexes']) }}">
                                <div class="tkt-attendee__head">
                                    <div class="tkt-attendee__title">
                                        {{ $group['is_couple'] ? 'Person '.($pos + 1).' of 2' : 'Ticket '.($pos + 1).' of '.count($group['indexes']) }}
                                        <span class="tkt-text-muted" style="font-weight:500">· {{ $group['name'] }}</span>
                                    </div>
                                    @if($i === 0)
                                        <label class="tkt-check tkt-same-as"><input type="checkbox" data-same-as-buyer @checked(old('same_as_buyer'))><span>Same as buyer</span></label>
                                    @endif
                                </div>
                                <div class="tkt-form-grid tkt-form-grid--2">
                                    <div class="tkt-field is-full">
                                        <label class="tkt-label" for="att_{{ $i }}_name">Full name</label>
                                        <input class="tkt-input {{ $errors->has("attendees.$i.name") ? 'is-invalid' : '' }}" id="att_{{ $i }}_name" name="attendees[{{ $i }}][name]" type="text" value="{{ $attendeeValue($i, 'name') }}" autocomplete="off" required data-att-name>
                                        @error("attendees.$i.name")<div class="tkt-field__error">{{ $message }}</div>@enderror
                                    </div>
                                    <div class="tkt-field">
                                        <label class="tkt-label" for="att_{{ $i }}_email">Email</label>
                                        <input class="tkt-input {{ $errors->has("attendees.$i.email") ? 'is-invalid' : '' }}" id="att_{{ $i }}_email" name="attendees[{{ $i }}][email]" type="email" inputmode="email" value="{{ $attendeeValue($i, 'email') }}" autocomplete="off" required data-att-email>
                                        @error("attendees.$i.email")<div class="tkt-field__error">{{ $message }}</div>@enderror
                                    </div>
                                    <div class="tkt-field">
                                        <label class="tkt-label" for="att_{{ $i }}_phone">Phone number</label>
                                        <input class="tkt-input {{ $errors->has("attendees.$i.phone") ? 'is-invalid' : '' }}" id="att_{{ $i }}_phone" name="attendees[{{ $i }}][phone]" type="tel" inputmode="tel" value="{{ $attendeeValue($i, 'phone') }}" autocomplete="off" placeholder="100 000 0000" required data-phone-intl data-att-phone>
                                        @error("attendees.$i.phone")<div class="tkt-field__error">{{ $message }}</div>@enderror
                                    </div>
                                    <div class="tkt-field">
                                        <span class="tkt-label" id="att_{{ $i }}_gender_l">Gender</span>
                                        <div class="tkt-gender {{ $errors->has("attendees.$i.gender") ? 'is-invalid' : '' }}" role="radiogroup" aria-labelledby="att_{{ $i }}_gender_l">
                                            @foreach(['male' => 'Male', 'female' => 'Female'] as $val => $label)
                                                <label><input type="radio" name="attendees[{{ $i }}][gender]" value="{{ $val }}" required @checked($attendeeValue($i, 'gender') === $val)><span>{{ $label }}</span></label>
                                            @endforeach
                                        </div>
                                        @error("attendees.$i.gender")<div class="tkt-field__error">{{ $message }}</div>@enderror
                                    </div>
                                    <div class="tkt-field">
                                        <label class="tkt-label" for="att_{{ $i }}_social">Instagram / social profile</label>
                                        <input class="tkt-input {{ $errors->has("attendees.$i.social_profile") ? 'is-invalid' : '' }}" id="att_{{ $i }}_social" name="attendees[{{ $i }}][social_profile]" type="text" value="{{ $attendeeValue($i, 'social_profile') }}" placeholder="@username" autocomplete="off" required>
                                        @error("attendees.$i.social_profile")<div class="tkt-field__error">{{ $message }}</div>@enderror
                                    </div>
                                </div>
                            </div>
                        @endforeach
                    </div>
                @endforeach
            </section>

            <div @if($wizard) data-wz-step="review" @endif>
            @if($wizard)
                <section class="tkt-card tkt-section-card" data-wz-recap aria-labelledby="recapTitle">
                    <h2 class="tkt-section-card__title" id="recapTitle">Review your booking</h2>
                    <p class="tkt-section-card__sub">Check the details below. You can edit any of them before paying.</p>
                    <div data-wz-recap-body></div>
                </section>
            @endif

            {{-- Promo --}}
            <section class="tkt-card tkt-section-card">
                <h2 class="tkt-section-card__title">Promo code</h2>
                <div class="tkt-promo" style="margin-top:12px">
                    <input class="tkt-input {{ $errors->has('promo_code') ? 'is-invalid' : '' }}" type="text" id="promoInput" value="{{ old('promo_code', $promoCode) }}" placeholder="ENTER CODE" autocomplete="off" aria-label="Promo code" maxlength="50" data-promo-url="{{ route('front.checkout.promo') }}">
                    <button type="button" class="tkt-btn tkt-btn--outline" id="promoApply">Apply</button>
                    <button type="button" class="tkt-btn tkt-btn--ghost" id="promoRemove" @if($promoCode === '') hidden @endif>Remove</button>
                </div>
                <div class="tkt-promo-msg {{ $errors->has('promo_code') ? 'is-error' : ($promoCode !== '' ? 'is-ok' : '') }}" id="promoMsg" role="status">
                    @if($errors->has('promo_code')){{ $errors->first('promo_code') }}@elseif($promoNotice){{ $promoNotice }}@elseif($promoCode !== ''){{ "Promo code {$promoCode} applied." }}@endif
                </div>
            </section>

            {{-- Price breakdown (mobile; desktop shows it in the sticky sidebar) --}}
            @unless($wizard)<div class="tkt-mobile-summary">@include('front.checkout._summary', ['event' => $event, 'quote' => $quote])</div>@endunless

            {{-- Payment --}}
            <section class="tkt-card tkt-section-card">
                <h2 class="tkt-section-card__title">Payment</h2>
                @if($requiresApproval)
                    <div class="tkt-alert tkt-alert--info" style="margin-top:12px">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><circle cx="12" cy="12" r="10"/><path d="M12 16v-4M12 8h.01"/></svg>
                        <div>This event confirms bookings manually. Submit your request now — if it is approved we email you a secure link to pay. You are not charged today.</div>
                    </div>
                @else
                    <p class="tkt-section-card__sub">You pay on the provider's secure page. We never see or store your card details.</p>
                    @if($errors->has('payment_method'))
                        <div class="tkt-alert tkt-alert--danger" role="alert" style="margin-bottom:12px">{{ $errors->first('payment_method') }}</div>
                    @endif
                    <div class="tkt-methods" role="radiogroup" aria-label="Payment method" id="methodGroup">
                        @forelse($methods as $method)
                            <label class="tkt-method">
                                <input type="radio" name="payment_method" value="{{ $method->code }}" @checked($chosenMethod === $method->code) required>
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
            </div>
        </form>

        <aside class="tkt-checkout__aside" aria-label="Order summary">
            @include('front.checkout._summary', ['event' => $event, 'quote' => $quote])
            <button type="submit" form="expressForm" class="tkt-btn tkt-btn--primary tkt-btn--lg tkt-btn--block" style="margin-top:12px" data-loading-target data-pay-label @if($wizard) data-wz-pay @endif @disabled(! $canPay)>{{ $payLabel }}</button>
                        <p class="tkt-ordersum__note"><svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" style="display:inline;vertical-align:-1px"><rect x="3" y="11" width="18" height="11" rx="2"/><path d="M7 11V7a5 5 0 0 1 10 0v4"/></svg> Secure payment · fees included in the total</p>
        </aside>
    </div>
</div>

<div class="tkt-bottombar" data-bottombar>
    <div class="tkt-bottombar__total"><small>Total</small><b data-sum-total-bar>{{ $money($quote->total) }}</b></div>
    <button type="submit" form="expressForm" class="tkt-btn tkt-btn--primary" data-loading-target data-pay-label @if($wizard) data-wz-pay @endif @disabled(! $canPay)>{{ $requiresApproval ? 'Submit request' : 'Pay now' }}</button>
</div>

@unless($wizard)
{{-- Small modal: confirm the mailbox of an existing account without leaving checkout --}}
<div class="modal fade tkt-modal" id="otpModal" tabindex="-1" role="dialog" aria-labelledby="otpTitle">
    <div class="modal-dialog" style="max-width:460px">
        <div class="modal-content">
            <div class="modal-header">
                <button type="button" class="close" data-dismiss="modal" aria-label="Close"><span aria-hidden="true">&times;</span></button>
                <h4 class="modal-title" id="otpTitle">Confirm your email</h4>
            </div>
            <div class="modal-body">
                <p class="tkt-text-sm" style="margin-bottom:12px">To keep your booking safe we need to confirm this email. We've sent a code if it can receive one. Your booking details are saved — nothing is lost.</p>
                <x-tkt.otp-panel email="" id-suffix="modal" />
                <p class="tkt-text-muted tkt-text-sm" style="margin-top:14px">Typed it wrong? <button type="button" class="tkt-link" style="background:none;border:0;padding:0;font-weight:700;text-decoration:underline" data-otp-edit-email>Edit my email</button> — your tickets and details stay as they are.</p>
            </div>
        </div>
    </div>
</div>
@endunless
@endsection

@push('scripts')
    <script src="{{ asset('js/tkt-otp.js') }}?v={{ filemtime(public_path('js/tkt-otp.js')) }}"></script>
    <script src="{{ asset('js/tkt-checkout.js') }}?v={{ filemtime(public_path('js/tkt-checkout.js')) }}"></script>
    @if($wizard)<script src="{{ asset('js/tkt-checkout-wizard.js') }}?v={{ filemtime(public_path('js/tkt-checkout-wizard.js')) }}"></script>@endif
    <script src="{{ asset('js/tkt-contact-email.js') }}?v={{ filemtime(public_path('js/tkt-contact-email.js')) }}"></script>
    <script src="{{ asset('js/tkt-express.js') }}?v={{ filemtime(public_path('js/tkt-express.js')) }}"></script>
@endpush
