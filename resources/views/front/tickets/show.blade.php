@extends('front.account.layout')
@section('title', 'Ticket · '.($event?->name ?? $ticket->ticket_name))
@section('heading', 'Your ticket')

@php
    [$label, $variant] = \App\Support\CustomerPortal::ticketBadge($ticket, $event);
    $image = $event?->cover_image_url ?: $event?->event_banner_url;
    $state = $qr['state'];
    $index = $siblings->search(fn ($s) => $s->id === $ticket->id);
    $prev = $index !== false && $index > 0 ? $siblings[$index - 1] : null;
    $next = $index !== false && $index < $siblings->count() - 1 ? $siblings[$index + 1] : null;
    $revealsAt = $qr['reveals_at'];
@endphp

@section('portal')
<a class="tkt-portal__back" href="{{ route('front.account.tickets', ['order' => $siblings->count() > 1 && $ticket->order ? $ticket->order->order_number : null, 'when' => $siblings->count() > 1 ? 'all' : null]) }}">
    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M19 12H5M11 6l-6 6 6 6"/></svg> {{ $siblings->count() > 1 ? 'All tickets of this order' : 'My tickets' }}
</a>

@if($siblings->count() > 1)
    <nav class="dt-pager" aria-label="Tickets in this order">
        @if($prev)<a class="dt-pager__btn" href="{{ route('front.tickets.show', $prev) }}" rel="prev" aria-label="Previous ticket"><svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round"><path d="m15 18-6-6 6-6"/></svg></a>@else<span class="dt-pager__btn is-off" aria-hidden="true"></span>@endif
        <div class="dt-pager__mid">
            <b>Ticket {{ ($index === false ? 0 : $index) + 1 }} of {{ $siblings->count() }}</b>
            <span class="dt-pager__dots">
                @foreach($siblings as $i => $sib)
                    <a href="{{ route('front.tickets.show', $sib) }}" class="{{ $sib->id === $ticket->id ? 'is-on' : '' }}" aria-label="Ticket {{ $i + 1 }}: {{ $sib->holder_name ?: 'Attendee' }}" @if($sib->id === $ticket->id) aria-current="true" @endif></a>
                @endforeach
            </span>
        </div>
        @if($next)<a class="dt-pager__btn" href="{{ route('front.tickets.show', $next) }}" rel="next" aria-label="Next ticket"><svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round"><path d="m9 18 6-6-6-6"/></svg></a>@else<span class="dt-pager__btn is-off" aria-hidden="true"></span>@endif
    </nav>
@endif

<article class="dt {{ 'dt--'.$state }}" aria-label="Ticket {{ $ticket->ticket_number }}"
    data-ticket-state="{{ $state }}" data-qr-url="{{ route('front.tickets.qr', $ticket) }}"
    @if($state === 'locked' && $revealsAt) data-reveals-at="{{ $revealsAt->toIso8601String() }}" data-server-now="{{ now()->toIso8601String() }}" @endif>

    <header class="dt__hero {{ $image ? '' : 'is-plain' }}" @if($image) style="--dt-img:url('{{ $image }}')" @endif>
        <div class="dt__hero-in">
            <x-tkt.badge :variant="$variant" :dot="true" class="dt__badge">{{ $label }}</x-tkt.badge>
            <h2 class="dt__event">{{ $event?->name ?? $ticket->ticket_name }}</h2>
            @if($event)
                <p class="dt__line"><svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><rect x="3" y="4" width="18" height="17" rx="2"/><path d="M16 2v4M8 2v4M3 10h18"/></svg>{{ $event->formatted_date_range }}@if($event->event_time) · {{ \Carbon\Carbon::parse($event->event_time)->format('g:i A') }}@endif</p>
                @if($event->location)<p class="dt__line"><svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M12 21s-7-6.2-7-11a7 7 0 0 1 14 0c0 4.8-7 11-7 11Z"/><circle cx="12" cy="10" r="2.5"/></svg>{{ $event->location }}</p>@endif
            @endif
        </div>
    </header>

    <div class="dt__tear" aria-hidden="true"><i></i><span></span><i></i></div>

    <section class="dt__qr" aria-live="polite">
        @if($state === 'visible')
            <div class="dt__qr-box" role="img" aria-label="Ticket QR code. Show it at the entrance.">{!! $qrSvg !!}</div>
            <p class="dt__qr-cap">Show this code at the entrance</p>
            <p class="dt__qr-tip">Tip: raise your screen brightness for faster scanning.</p>
        @elseif($state === 'locked')
            <div class="dt__lock">
                {{-- Decorative placeholder: the same fixed picture for every ticket, never derived from the real QR. --}}
                <div class="dt__ph" aria-hidden="true">
                    {!! \App\Support\TicketQr::placeholderSvg() !!}
                    <div class="dt__ph-veil">
                        <span class="dt__ph-lock"><svg viewBox="0 0 24 24" width="26" height="26" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><rect x="4" y="11" width="16" height="10" rx="2"/><path d="M8 11V8a4 4 0 0 1 8 0v3"/></svg></span>
                        <b>QR LOCKED</b>
                        @if($revealsAt)<small>Opens {{ $revealsAt->format('D j M · g:i A') }}</small>@endif
                    </div>
                </div>
                <h3>QR Code Locked</h3>
                <p>Your QR code will be available closer to the event.</p>
                @if($revealsAt)
                    <p class="dt__when">Available <b>{{ $revealsAt->format('D, j M Y · g:i A') }}</b></p>
                    <div class="dt__count" data-countdown aria-label="Time until your QR code is available"></div>
                @endif
                <p class="dt__valid"><svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="m5 12 5 5 9-10"/></svg> Your ticket is confirmed and valid</p>
            </div>
        @elseif($state === 'used')
            <div class="dt__off"><b>Checked in</b><p>This ticket has already been scanned at the entrance{{ $ticket->dashboardTicket?->checked_in_at ? ' on '.$ticket->dashboardTicket->checked_in_at->format('j M, g:i A') : '' }}.</p></div>
        @else
            <div class="dt__off"><b>{{ $qr['refunded'] ? 'Refunded' : 'Canceled' }}</b><p>{{ $qr['refunded'] ? 'This ticket was refunded and can no longer be used.' : 'This ticket was canceled and can no longer be used.' }}</p></div>
        @endif
    </section>

    <dl class="dt__meta">
        <div><dt>Attendee</dt><dd>{{ $ticket->holder_name ?: '—' }}</dd></div>
        <div><dt>Ticket type</dt><dd>{{ $ticket->ticketTypeLabel() }}</dd></div>
        @if($isBuyer || ! $ticket->order)
        <div><dt>{{ $ticket->order ? 'Order' : 'Type' }}</dt><dd>
            @if(! $ticket->order)
                Guest invitation
            @else
                <a class="tkt-link" href="{{ route('front.account.orders.show', $ticket->order->order_number) }}">#{{ $ticket->order->order_number }}</a>
            @endif
        </dd></div>
        @endif
        <div><dt>Ticket no.</dt><dd class="dt__mono">{{ $ticket->ticket_number }}</dd></div>
        <div><dt>Status</dt><dd>{{ $label }}</dd></div>
    </dl>
</article>
@endsection

@push('scripts')
<script>
(function () {
    var root = document.querySelector('.dt'); if (!root) return;
    var state = root.getAttribute('data-ticket-state');
    if (state === 'canceled' || state === 'used') return;
    var url = root.getAttribute('data-qr-url');

    // Locked -> visible: the real QR takes the placeholder's place, same size, with a short fade (no reload, no layout jump).
    function reveal(svg) {
        var panel = root.querySelector('.dt__qr'), ph = root.querySelector('.dt__ph');
        if (!panel || !ph) { window.location.reload(); return; }
        var box = document.createElement('div');
        box.className = 'dt__qr-box is-in'; box.setAttribute('role', 'img'); box.setAttribute('aria-label', 'Ticket QR code. Show it at the entrance.');
        box.innerHTML = svg;
        ph.classList.add('is-out');
        setTimeout(function () {
            panel.innerHTML = '';
            panel.appendChild(box);
            var cap = document.createElement('p'); cap.className = 'dt__qr-cap'; cap.textContent = 'Show this code at the entrance'; panel.appendChild(cap);
            var tip = document.createElement('p'); tip.className = 'dt__qr-tip'; tip.textContent = 'Tip: raise your screen brightness for faster scanning.'; panel.appendChild(tip);
            root.classList.remove('dt--locked'); root.classList.add('dt--visible'); root.setAttribute('data-ticket-state', 'visible'); state = 'visible';
            clearInterval(window.__dtTimer);
        }, 350);
    }

    function check() {
        fetch(url, { headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' }, credentials: 'same-origin', cache: 'no-store' })
            .then(function (r) { return r.ok ? r.json() : null; })
            .then(function (d) {
                if (!d || d.state === state) return;
                if (state === 'locked' && d.state === 'visible' && d.svg) { reveal(d.svg); } else { window.location.reload(); }
            })
            .catch(function () {});
    }

    // Countdown to the reveal moment (server time as the reference, so a wrong phone clock does not matter).
    var at = root.getAttribute('data-reveals-at'), box = root.querySelector('[data-countdown]');
    if (at && box) {
        var skew = new Date(root.getAttribute('data-server-now')).getTime() - Date.now();
        var target = new Date(at).getTime();
        var cells = function (n, l) { return '<span><b>' + String(n).padStart(2, '0') + '</b><small>' + l + '</small></span>'; };
        var tick = function () {
            var left = Math.max(0, target - (Date.now() + skew)), s = Math.floor(left / 1000);
            var d = Math.floor(s / 86400), h = Math.floor(s % 86400 / 3600), m = Math.floor(s % 3600 / 60), sec = s % 60;
            box.innerHTML = (d > 0 ? cells(d, 'days') : '') + cells(h, 'hrs') + cells(m, 'min') + (d === 0 ? cells(sec, 'sec') : '');
            if (left === 0) { clearInterval(timer); check(); }
        };
        var timer = window.__dtTimer = setInterval(tick, 1000); tick();
    }

    // Admin changes (show / hide) reach an open page within a minute, and straight away when the tab comes back.
    setInterval(check, 60000);
    document.addEventListener('visibilitychange', function () { if (!document.hidden) check(); });
})();
</script>
@endpush
