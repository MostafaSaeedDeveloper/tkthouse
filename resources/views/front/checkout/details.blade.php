@extends('front.layout.app')

@section('title', 'Your details')

@php
    $old = fn (string $key, $default = '') => old($key, $default);
    $attendeeValue = fn (int $i, string $field) => old("attendees.$i.$field", data_get($attendees, "$i.$field", ''));
    $money = fn ($n) => number_format((float) $n, 2).' EGP';
    $buyerName = trim(old('full_name', trim(($buyer['first_name'] ?? '').' '.($buyer['last_name'] ?? ''))));
    $buyerPhone = old('phone', $buyer['phone'] ?? '');
    $hasErrors = $errors->any();
@endphp

@section('content')
<div class="tkt-container tkt-checkout">
    <x-tkt.stepper :current="2" />

    <div class="tkt-checkout__head">
        <a class="tkt-back" style="color:var(--tkt-muted);margin:0 0 8px;display:inline-flex" href="{{ route('front.events.show', $event) }}">
            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M19 12H5M11 6l-6 6 6 6"/></svg>
            Change tickets
        </a>
        <h1 class="tkt-checkout__title">Your details</h1>
    </div>

    <div class="tkt-checkout__layout">
        <form class="tkt-checkout__main" id="checkoutDetailsForm" method="POST" action="{{ route('front.checkout.details') }}" data-checkout-form data-tkt-guard>
            @csrf

            @if($hasErrors)
                <div class="tkt-alert tkt-alert--danger" role="alert" id="formErrors">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><circle cx="12" cy="12" r="10"/><path d="M12 8v4M12 16h.01"/></svg>
                    <div>Please check the highlighted fields and try again.</div>
                </div>
            @endif

            {{-- Buyer --}}
            <section class="tkt-card tkt-section-card" aria-labelledby="buyerTitle">
                <h2 class="tkt-section-card__title" id="buyerTitle">Buyer information</h2>
                <p class="tkt-section-card__sub">We send your order confirmation here.</p>
                <div class="tkt-form-grid tkt-form-grid--2">
                    @php $nameError = $errors->first('first_name') ?: $errors->first('last_name'); @endphp
                    <div class="tkt-field is-full">
                        <label class="tkt-label" for="buyer_name">Full name</label>
                        <input class="tkt-input {{ $nameError ? 'is-invalid' : '' }}" id="buyer_name" name="full_name" type="text" value="{{ $buyerName }}" autocomplete="name" placeholder="Jane Doe" required data-buyer-name>
                        @if($nameError)<div class="tkt-field__error">{{ $nameError }}</div>@endif
                    </div>
                    <div class="tkt-field">
                        <label class="tkt-label" for="buyer_email">Email address</label>
                        <input class="tkt-input {{ $errors->has('email') ? 'is-invalid' : '' }}" id="buyer_email" name="email" type="email" inputmode="email" value="{{ old('email', $buyer['email'] ?? '') }}" autocomplete="email" placeholder="jane@domain.com" required data-buyer-email>
                        @error('email')<div class="tkt-field__error">{{ $message }}</div>@enderror
                    </div>
                    <div class="tkt-field">
                        <label class="tkt-label" for="buyer_phone">Phone number <span class="tkt-text-muted" style="font-weight:500">(optional)</span></label>
                        <input class="tkt-input {{ $errors->has('phone') ? 'is-invalid' : '' }}" id="buyer_phone" name="phone" type="tel" inputmode="tel" value="{{ $buyerPhone }}" autocomplete="tel" placeholder="100 000 0000" data-phone-intl data-buyer-phone>
                        @error('phone')<div class="tkt-field__error">{{ $message }}</div>@enderror
                    </div>
                </div>
            </section>

            {{-- Attendees, grouped by ticket type --}}
            <section class="tkt-card tkt-section-card" aria-labelledby="attendeesTitle">
                <h2 class="tkt-section-card__title" id="attendeesTitle">Attendee information</h2>
                <p class="tkt-section-card__sub">Each ticket needs the details of the person who will attend. Entry is checked against these names.</p>

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
                            <div class="tkt-attendee" data-attendee="{{ $i }}">
                                <div class="tkt-attendee__head">
                                    <div class="tkt-attendee__title">
                                        {{ $group['is_couple'] ? 'Person '.($pos + 1).' of 2' : 'Ticket '.($pos + 1).' of '.count($group['indexes']) }}
                                        <span class="tkt-text-muted" style="font-weight:500">· {{ $group['name'] }}</span>
                                    </div>
                                    @if($i === 0)
                                        <label class="tkt-check tkt-same-as"><input type="checkbox" data-same-as-buyer @checked(old('same_as_buyer')) ><span>Same as buyer</span></label>
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
                                                <label><input type="radio" name="attendees[{{ $i }}][gender]" value="{{ $val }}" required @checked($attendeeValue($i, 'gender') === $val) data-att-gender><span>{{ $label }}</span></label>
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

            <div class="tkt-mobile-summary">@include('front.checkout._summary', ['event' => $event, 'quote' => $quote])</div>
        </form>

        <aside class="tkt-checkout__aside" aria-label="Order summary">
            @include('front.checkout._summary', ['event' => $event, 'quote' => $quote])
            <button type="submit" form="checkoutDetailsForm" class="tkt-btn tkt-btn--primary tkt-btn--lg tkt-btn--block" style="margin-top:12px" data-loading-target>Continue to payment</button>
        </aside>
    </div>
</div>

<div class="tkt-bottombar" data-bottombar>
    <div class="tkt-bottombar__total"><small>Total · {{ $quote->people }} {{ \Illuminate\Support\Str::plural('ticket', $quote->people) }}</small><b>{{ $money($quote->total) }}</b></div>
    <button type="submit" form="checkoutDetailsForm" class="tkt-btn tkt-btn--primary" data-loading-target>Continue</button>
</div>
@endsection

@push('scripts')
    <script src="{{ asset('js/tkt-checkout.js') }}?v={{ filemtime(public_path('js/tkt-checkout.js')) }}"></script>
@endpush
