@extends('front.account.layout')
@section('title', 'Order '.$order->order_number)
@section('heading', 'Order details')

@php
    $money = fn ($n) => number_format((float) $n, 2).' EGP';
    $event = \App\Support\CustomerPortal::eventOf($order);
    $image = $event?->cover_image_url ?: $event?->event_banner_url;
    [$orderLabel, $orderVariant] = \App\Support\CustomerPortal::orderBadge($order->status);
    [$payLabel, $payVariant] = \App\Support\CustomerPortal::paymentBadge($order);
    $count = (int) $order->items->sum('quantity');
    $feesAndOther = round((float) $order->total_amount - ((float) $order->subtotal_amount - (float) $order->discount_amount), 2);
    $payUrl = $order->status === 'pending_payment' && $order->payment_link_token
        ? route('front.orders.payment', ['order' => $order, 'token' => $order->payment_link_token]) : null;
    $failed = $payLabel === 'Payment failed';
@endphp

@section('portal')
<a class="tkt-portal__back" href="{{ route('front.account.orders') }}">
    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M19 12H5M11 6l-6 6 6 6"/></svg> All orders
</a>

<div class="tkt-portal__split">
    <div class="tkt-portal__main">
        {{-- Header + status --}}
        <section class="tkt-card tkt-pcard">
            <div class="tkt-pcard__row">
                <div>
                    <p class="tkt-pcard__eyebrow">Order</p>
                    <h2 class="tkt-pcard__title">{{ $order->order_number }}</h2>
                    <p class="tkt-text-muted tkt-text-sm">Booked {{ $order->created_at->format('d M Y, g:i A') }}</p>
                </div>
                <div class="tkt-order-card__badges">
                    <x-tkt.badge :variant="$orderVariant">{{ $orderLabel }}</x-tkt.badge>
                    @if($payLabel !== $orderLabel)<x-tkt.badge :variant="$payVariant">{{ $payLabel }}</x-tkt.badge>@endif
                </div>
            </div>

            @if($order->status === 'pending_payment')
                <div class="tkt-alert {{ $failed ? 'tkt-alert--danger' : 'tkt-alert--warning' }}" role="status" style="margin-top:14px">
                    <div>
                        @if($failed)<strong>Your payment did not go through.</strong> You were not charged for the failed attempt. You can try again below.
                        @elseif($payLabel === 'Processing')<strong>We are waiting for your payment to be confirmed.</strong> This usually takes a moment — refresh this page shortly.
                        @else<strong>This order is waiting for payment.</strong> Complete it to receive your tickets.@endif
                    </div>
                </div>
                @if($payUrl)
                    <a class="tkt-btn tkt-btn--primary tkt-btn--lg" style="margin-top:14px" href="{{ $payUrl }}">{{ $failed ? 'Try payment again' : 'Complete payment' }}</a>
                @endif
            @elseif($order->status === 'pending_approval')
                <div class="tkt-alert tkt-alert--info" style="margin-top:14px"><div>Your booking request is waiting for approval. You are not charged until it is approved — we email you a secure payment link.</div></div>
            @elseif(in_array($order->status, ['canceled', 'cancelled', 'rejected', 'expired'], true))
                <div class="tkt-alert tkt-alert--danger" style="margin-top:14px"><div>This order is {{ strtolower($orderLabel) }}. No tickets are active for it.</div></div>
            @elseif(in_array($order->status, ['refunded', 'partially_refunded'], true))
                <div class="tkt-alert tkt-alert--info" style="margin-top:14px"><div>This order was {{ strtolower($orderLabel) }}.</div></div>
            @endif
        </section>

        {{-- Event --}}
        <section class="tkt-card tkt-pcard">
            <h2 class="tkt-pcard__h">Event</h2>
            <div class="tkt-pevent">
                <div class="tkt-media tkt-pevent__img {{ $image ? '' : 'is-empty' }}">@if($image)<img class="tkt-img" src="{{ $image }}" alt="" loading="lazy">@endif</div>
                <div class="tkt-pevent__body">
                    <b>{{ $event?->name ?? 'Event' }}</b>
                    @if($event)<span>{{ $event->formatted_date_range }}@if($event->event_time) · {{ \Carbon\Carbon::parse($event->event_time)->format('g:i A') }}@endif</span>@endif
                    @if($event?->location)<span>{{ $event->location }}</span>@endif
                    @if($event && $event->slug)<a class="tkt-link" href="{{ route('front.events.show', $event) }}">View event page</a>@endif
                </div>
            </div>
        </section>

        {{-- Tickets --}}
        <section class="tkt-card tkt-pcard">
            <h2 class="tkt-pcard__h">Tickets &amp; attendees <span class="tkt-text-muted" style="font-weight:600">({{ $count }})</span></h2>
            @if($order->status === 'paid' && $order->issuedTickets->isNotEmpty())
                <ul class="tkt-pticket-list">
                    @foreach($order->issuedTickets->sortBy('id') as $ticket)
                        @php
                            $tEvent = $ticket->orderItem?->event ?? $event;
                            [$tLabel, $tVariant] = \App\Support\CustomerPortal::ticketBadge($ticket, $tEvent);
                        @endphp
                        <li>
                            <div class="tkt-pticket-list__text">
                                <b>{{ $ticket->holder_name ?: 'Attendee' }}</b>
                                <span>{{ $ticket->ticket_name }} · #{{ $ticket->ticket_number }}</span>
                                @if($ticket->holder_email)<small>{{ $ticket->holder_email }}</small>@endif
                            </div>
                            <x-tkt.badge :variant="$tVariant">{{ $tLabel }}</x-tkt.badge>
                            <a class="tkt-btn tkt-btn--outline tkt-btn--sm" href="{{ route('front.tickets.show', $ticket) }}">View ticket</a>
                        </li>
                    @endforeach
                </ul>
            @else
                <ul class="tkt-pticket-list">
                    @foreach($order->items as $item)
                        <li>
                            <div class="tkt-pticket-list__text">
                                <b>{{ $item->holder_name ?: 'Attendee' }}</b>
                                <span>{{ $item->ticket_name }} × {{ $item->quantity }}</span>
                                @if($item->holder_email)<small>{{ $item->holder_email }}</small>@endif
                            </div>
                        </li>
                    @endforeach
                </ul>
                @if($order->status !== 'paid')<p class="tkt-text-muted tkt-text-sm" style="margin-top:10px">Tickets are issued once the payment is confirmed.</p>@endif
            @endif
        </section>
    </div>

    <aside class="tkt-portal__side">
        <section class="tkt-card tkt-pcard">
            <h2 class="tkt-pcard__h">Payment</h2>
            <dl class="tkt-pbreak">
                <div><dt>Subtotal</dt><dd>{{ $money($order->subtotal_amount) }}</dd></div>
                @if((float) $order->discount_amount > 0)<div class="is-good"><dt>Discount @if($order->promo_code)<small>{{ $order->promo_code }}</small>@endif</dt><dd>−{{ $money($order->discount_amount) }}</dd></div>@endif
                @if($feesAndOther > 0)<div><dt>Booking fees</dt><dd>{{ $money($feesAndOther) }}</dd></div>@endif
                <div class="is-total"><dt>Total</dt><dd>{{ $money($order->total_amount) }}</dd></div>
            </dl>
            <dl class="tkt-pbreak tkt-pbreak--meta">
                <div><dt>Status</dt><dd><x-tkt.badge :variant="$payVariant">{{ $payLabel }}</x-tkt.badge></dd></div>
                @if($paymentMethodLabel)<div><dt>Method</dt><dd>{{ $paymentMethodLabel }}</dd></div>@endif
                @if($order->paid_at)<div><dt>Paid on</dt><dd>{{ $order->paid_at->format('d M Y, g:i A') }}</dd></div>@endif
            </dl>
        </section>

        <section class="tkt-card tkt-pcard">
            <h2 class="tkt-pcard__h">Buyer</h2>
            <dl class="tkt-pbreak tkt-pbreak--meta">
                <div><dt>Name</dt><dd>{{ trim(($order->customer?->first_name ?? '').' '.($order->customer?->last_name ?? '')) ?: '—' }}</dd></div>
                <div><dt>Email</dt><dd class="tkt-wrap">{{ $order->customer?->email ?: '—' }}</dd></div>
                @if($order->customer?->phone)<div><dt>Phone</dt><dd>{{ $order->customer->phone }}</dd></div>@endif
            </dl>
        </section>
    </aside>
</div>
@endsection
