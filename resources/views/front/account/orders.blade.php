@extends('front.account.layout')
@section('title', 'My orders')
@section('heading', 'My orders')

@php
    $money = fn ($n) => number_format((float) $n, 2).' EGP';
@endphp

@section('portal')
<section aria-labelledby="ordersTitle">
    <form class="tkt-portal__toolbar" method="GET" action="{{ route('front.account.orders') }}" role="search">
        <label class="tkt-sr-only" for="orderSearch">Search orders</label>
        <div class="tkt-portal__search">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><circle cx="11" cy="11" r="7"/><path d="m21 21-4.3-4.3"/></svg>
            <input class="tkt-input" id="orderSearch" type="search" name="q" value="{{ $search }}" placeholder="Search by order number or event" maxlength="80">
        </div>
        @if($filter !== 'all')<input type="hidden" name="status" value="{{ $filter }}">@endif
        <button class="tkt-btn tkt-btn--dark" type="submit">Search</button>
    </form>

    <div class="tkt-portal__chips" role="group" aria-label="Filter orders">
        @foreach($filters as $key => $label)
            <a href="{{ route('front.account.orders', array_filter(['status' => $key === 'all' ? null : $key, 'q' => $search ?: null])) }}" class="tkt-chip {{ $filter === $key ? 'is-active' : '' }}" @if($filter === $key) aria-current="true" @endif>{{ $label }}</a>
        @endforeach
    </div>

    @if($orders->isEmpty())
        <x-tkt.empty-state icon="ticket" :title="($search !== '' || $filter !== 'all') ? 'No orders match your search' : 'No orders yet'" :text="($search !== '' || $filter !== 'all') ? 'Try a different search or filter.' : 'When you book tickets, your orders appear here.'">
            @if($search !== '' || $filter !== 'all')
                <a class="tkt-btn tkt-btn--outline" href="{{ route('front.account.orders') }}">Clear filters</a>
            @else
                <a class="tkt-btn tkt-btn--primary" href="{{ route('front.events') }}">Browse events</a>
            @endif
        </x-tkt.empty-state>
    @else
        <ul class="tkt-portal__list" aria-label="Orders">
            @foreach($orders as $order)
                @php
                    $event = \App\Support\CustomerPortal::eventOf($order);
                    $image = $event?->cover_image_url ?: $event?->event_banner_url;
                    $count = (int) $order->items->sum('quantity');
                    [$orderLabel, $orderVariant] = \App\Support\CustomerPortal::orderBadge($order->status);
                    [$payLabel, $payVariant] = \App\Support\CustomerPortal::paymentBadge($order);
                @endphp
                <li class="tkt-order-card">
                    <a class="tkt-order-card__media tkt-media {{ $image ? '' : 'is-empty' }}" href="{{ route('front.account.orders.show', $order->order_number) }}" tabindex="-1" aria-hidden="true">
                        @if($image)<img class="tkt-img" src="{{ $image }}" alt="" loading="lazy">@endif
                    </a>
                    <div class="tkt-order-card__body">
                        <div class="tkt-order-card__top">
                            <span class="tkt-order-card__ref">Order {{ $order->order_number }}</span>
                            <span class="tkt-order-card__badges">
                                <x-tkt.badge :variant="$orderVariant">{{ $orderLabel }}</x-tkt.badge>
                                @if($payLabel !== $orderLabel)<x-tkt.badge :variant="$payVariant">{{ $payLabel }}</x-tkt.badge>@endif
                            </span>
                        </div>
                        <h2 class="tkt-order-card__title">{{ $event?->name ?? $order->items->first()?->ticket_name ?? 'Event' }}</h2>
                        <dl class="tkt-order-card__meta">
                            <div><dt>Booked</dt><dd>{{ $order->created_at->format('d M Y') }}</dd></div>
                            <div><dt>Tickets</dt><dd>{{ $count }}</dd></div>
                            <div><dt>Total</dt><dd>{{ $money($order->total_amount) }}</dd></div>
                        </dl>
                    </div>
                    <div class="tkt-order-card__action">
                        <a class="tkt-btn tkt-btn--outline" href="{{ route('front.account.orders.show', $order->order_number) }}">View details<span class="tkt-sr-only"> for order {{ $order->order_number }}</span></a>
                    </div>
                </li>
            @endforeach
        </ul>
        {{ $orders->links('vendor.pagination.tkt') }}
    @endif
</section>
@endsection
