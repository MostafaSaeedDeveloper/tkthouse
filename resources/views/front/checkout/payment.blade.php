@extends('front.layout.app')

@section('title', 'Complete payment')

@php
    $money = fn ($n) => number_format((float) $n, 2).' EGP';
    $time = $event?->event_time ? \Carbon\Carbon::parse($event->event_time)->format('g:i A') : null;
    $thumb = $event ? ($event->cover_image_url ?: $event->event_banner_url) : null;
    $deadline = $order->paymentDeadlineAt();
@endphp

@section('content')
<div class="tkt-container tkt-checkout">
    <x-tkt.stepper :current="3" />

    <div class="tkt-checkout__head">
        <h1 class="tkt-checkout__title">Complete your payment</h1>
        <p class="tkt-text-muted tkt-text-sm" style="margin-top:4px">Order <b>{{ $order->order_number }}</b>@if($deadline) · pay before {{ $deadline->format('d M Y, g:i A') }}@endif</p>
    </div>

    <div class="tkt-checkout__layout">
        <form class="tkt-checkout__main" id="payForm" method="GET" action="{{ route('front.orders.payment.gateway', ['order' => $order, 'token' => $order->payment_link_token]) }}" data-checkout-form data-place-order>
            @if($errors->any())
                <div class="tkt-alert tkt-alert--danger" role="alert">{{ $errors->first() }}</div>
            @elseif($lastFailure)
                <div class="tkt-alert tkt-alert--warning" role="status">Your last payment attempt was not completed and you were not charged. Choose a method and try again.</div>
            @endif

            <section class="tkt-card tkt-section-card">
                <h2 class="tkt-section-card__title">Payment method</h2>
                <p class="tkt-section-card__sub">You'll complete payment on the provider's secure page. We never see or store your card details.</p>
                <div class="tkt-methods" role="radiogroup" aria-label="Payment method">
                    @forelse($methods as $method)
                        <label class="tkt-method">
                            <input type="radio" name="method" value="{{ $method->code }}" @checked($selectedMethod === $method->code) required>
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
            </section>
        </form>

        <aside class="tkt-checkout__aside" style="display:block" aria-label="Order summary">
            <div class="tkt-card tkt-ordersum">
                @if($event)
                    <div class="tkt-ordersum__event">
                        <div class="tkt-media tkt-ordersum__thumb {{ $thumb ? '' : 'is-empty' }}">@if($thumb)<img class="tkt-img" src="{{ $thumb }}" alt="" loading="lazy">@endif</div>
                        <div style="min-width:0">
                            <div class="tkt-ordersum__event-name">{{ $event->name }}</div>
                            <div class="tkt-ordersum__event-meta">{{ $event->formatted_date_range }}@if($time) · {{ $time }}@endif @if($event->location)<br>{{ $event->location }}@endif</div>
                        </div>
                    </div>
                @endif
                <div class="tkt-ordersum__lines">
                    @foreach($order->items->groupBy('ticket_name') as $name => $rows)
                        <div class="tkt-ordersum__row"><span>{{ $name }} × {{ $rows->sum('quantity') }}</span><span>{{ $money($rows->sum('line_total')) }}</span></div>
                    @endforeach
                    @php $fees = round((float) $order->total_amount - ((float) $order->subtotal_amount - (float) $order->discount_amount), 2); @endphp
                    @if((float) $order->discount_amount > 0)
                        <div class="tkt-ordersum__row tkt-ordersum__row--discount"><span>Discount <small>{{ $order->promo_code }}</small></span><span>−{{ $money($order->discount_amount) }}</span></div>
                    @endif
                    @if($fees > 0)
                        <div class="tkt-ordersum__row tkt-ordersum__row--fee"><span>Booking fees</span><span>{{ $money($fees) }}</span></div>
                    @endif
                </div>
                <hr class="tkt-ordersum__rule">
                <div class="tkt-ordersum__total"><span>Total</span><b>{{ $money($order->total_amount) }}</b></div>
                <div class="tkt-ordersum__cta">
                    <button type="submit" form="payForm" class="tkt-btn tkt-btn--primary tkt-btn--lg tkt-btn--block" data-loading-target @disabled($methods->isEmpty())>Pay {{ $money($order->total_amount) }}</button>
                </div>
                <p class="tkt-ordersum__note">Secure payment · Booking fees are included in the total.</p>
            </div>
        </aside>
    </div>
</div>
@endsection

@push('scripts')
    <script src="{{ asset('js/tkt-checkout.js') }}?v={{ filemtime(public_path('js/tkt-checkout.js')) }}"></script>
@endpush
