@extends('front.layout.app')

@section('title', match($state) { 'paid' => 'You\'re in!', 'pending' => 'Payment pending', 'failed' => 'Payment failed', 'review' => 'Request received', default => 'Your order' })

@php
    $money = fn ($n) => number_format((float) $n, 2).' EGP';
    $payUrl = $order->payment_link_token ? route('front.orders.payment', ['order' => $order, 'token' => $order->payment_link_token]) : null;
    $time = $event?->event_time ? \Carbon\Carbon::parse($event->event_time)->format('g:i A') : null;
    $iconCheck = '<path d="M20 6 9 17l-5-5"/>';
@endphp

@section('content')
<div class="tkt-container tkt-checkout">
    <x-tkt.stepper :current="4" />

    <div class="tkt-result">
        <section class="tkt-card tkt-result__card" aria-live="polite" @if($state === 'pending') data-autorefresh="12000" @endif>
            @switch($state)
                @case('paid')
                    <div class="tkt-result__icon tkt-result__icon--success"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">{!! $iconCheck !!}</svg></div>
                    <h1 class="tkt-result__title">You're in!</h1>
                    <p class="tkt-result__text">Your payment was confirmed. Your tickets have been sent to {{ $order->customer?->email ?: 'your email' }}.</p>
                    @break
                @case('pending')
                    <div class="tkt-result__icon tkt-result__icon--pending"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><circle cx="12" cy="12" r="10"/><path d="M12 6v6l4 2"/></svg></div>
                    <h1 class="tkt-result__title">Payment pending</h1>
                    <p class="tkt-result__text">We have not received confirmation from the payment provider yet. This page checks automatically — please don't pay again. If you were charged, your tickets will arrive by email within a few minutes.</p>
                    @break
                @case('failed')
                    <div class="tkt-result__icon tkt-result__icon--failed"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.6" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M18 6 6 18M6 6l12 12"/></svg></div>
                    <h1 class="tkt-result__title">Payment failed</h1>
                    <p class="tkt-result__text">The payment was not completed and you have not been charged. Your order is saved — you can safely try again.</p>
                    @break
                @case('review')
                    <div class="tkt-result__icon tkt-result__icon--info"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><circle cx="12" cy="12" r="10"/><path d="M12 16v-4M12 8h.01"/></svg></div>
                    <h1 class="tkt-result__title">Request received</h1>
                    <p class="tkt-result__text">Your booking is waiting for approval. If it is approved we will email you a secure payment link. You have not been charged.</p>
                    @break
                @case('unpaid')
                    <div class="tkt-result__icon tkt-result__icon--pending"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><rect x="2" y="5" width="20" height="14" rx="2"/><path d="M2 10h20"/></svg></div>
                    <h1 class="tkt-result__title">Complete your payment</h1>
                    <p class="tkt-result__text">Your order is reserved for you. Finish paying to receive your tickets.</p>
                    @break
                @default
                    <div class="tkt-result__icon tkt-result__icon--failed"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.6" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M18 6 6 18M6 6l12 12"/></svg></div>
                    <h1 class="tkt-result__title">Order closed</h1>
                    <p class="tkt-result__text">This order is no longer active ({{ str_replace('_', ' ', $order->status) }}). If you were charged, please contact support with your order reference.</p>
            @endswitch

            <div class="tkt-result__ref">Order reference <b>{{ $order->order_number }}</b></div>

            @if($event)
                <div class="tkt-ordersum__event" style="text-align:left;margin-top:24px;border-bottom:0;padding-bottom:0">
                    @php $thumb = $event->cover_image_url ?: $event->event_banner_url; @endphp
                    <div class="tkt-media tkt-ordersum__thumb {{ $thumb ? '' : 'is-empty' }}">@if($thumb)<img class="tkt-img" src="{{ $thumb }}" alt="" loading="lazy">@endif</div>
                    <div style="min-width:0">
                        <div class="tkt-ordersum__event-name">{{ $event->name }}</div>
                        <div class="tkt-ordersum__event-meta">{{ $event->formatted_date_range }}@if($time) · {{ $time }}@endif @if($event->location)<br>{{ $event->location }}@endif</div>
                    </div>
                </div>
            @endif

            @if($state === 'paid')
                <div class="tkt-ticketlist">
                    @forelse($tickets as $ticket)
                        <div class="tkt-tkt">
                            <div class="tkt-tkt__info">
                                <div class="tkt-tkt__name">{{ $ticket->holder_name }}</div>
                                <div class="tkt-tkt__meta">{{ $ticket->ticket_name }} · #{{ $ticket->ticket_number }}</div>
                            </div>
                            <x-tkt.badge variant="success" :dot="true">Issued</x-tkt.badge>
                            <div class="tkt-tkt__actions">
                                <a class="tkt-btn tkt-btn--outline tkt-btn--sm" href="{{ route('front.tickets.show', $ticket) }}">View</a>
                                <a class="tkt-btn tkt-btn--outline tkt-btn--sm" href="{{ route('front.tickets.download', $ticket) }}">PDF</a>
                            </div>
                        </div>
                    @empty
                        <div class="tkt-alert tkt-alert--info">Your tickets are being prepared and will appear in My tickets in a moment.</div>
                    @endforelse
                </div>
            @endif

            <div class="tkt-result__actions">
                @if($state === 'paid')
                    <a class="tkt-btn tkt-btn--primary tkt-btn--lg" href="{{ route('front.account.tickets') }}">View my tickets</a>
                    <a class="tkt-btn tkt-btn--outline tkt-btn--lg" href="{{ route('front.events') }}">Back to events</a>
                @elseif($state === 'pending')
                    <a class="tkt-btn tkt-btn--primary tkt-btn--lg" href="{{ route('front.checkout.confirmation', $order->order_number) }}">Check payment status</a>
                    @if($payUrl)<a class="tkt-btn tkt-btn--outline tkt-btn--lg" href="{{ $payUrl }}">Use another method</a>@endif
                @elseif(in_array($state, ['failed', 'unpaid'], true) && $payUrl)
                    <a class="tkt-btn tkt-btn--primary tkt-btn--lg" href="{{ $payUrl }}">{{ $state === 'failed' ? 'Try again' : 'Pay now' }}</a>
                    <a class="tkt-btn tkt-btn--outline tkt-btn--lg" href="{{ route('front.account.orders') }}">My orders</a>
                @else
                    <a class="tkt-btn tkt-btn--primary tkt-btn--lg" href="{{ route('front.account.orders') }}">My orders</a>
                    <a class="tkt-btn tkt-btn--outline tkt-btn--lg" href="{{ route('front.events') }}">Browse events</a>
                @endif
            </div>
        </section>
    </div>
</div>
@endsection

@push('scripts')
    <script src="{{ asset('js/tkt-checkout.js') }}?v={{ filemtime(public_path('js/tkt-checkout.js')) }}"></script>
@endpush
