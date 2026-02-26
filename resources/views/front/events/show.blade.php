@extends('front.layout.master')

@section('content')

<style>
    @import url('https://fonts.googleapis.com/css2?family=Bebas+Neue&family=Barlow:wght@300;400;500;600;700&display=swap');

    /* ── TICKET BOOKING SECTION ─────────────────────────────── */
    .tkt-booking-section {
        background: #0a0a0a;
        padding: 70px 0 90px;
        border-top: 1px solid rgba(255,255,255,0.06);
    }

    .tkt-booking-section .section-label {
        display: flex;
        align-items: center;
        gap: 14px;
        margin-bottom: 10px;
    }
    .tkt-booking-section .section-label span {
        font-family: 'Barlow', sans-serif;
        font-size: 10px;
        font-weight: 700;
        letter-spacing: 6px;
        text-transform: uppercase;
        color: #f4c430;
    }
    .tkt-booking-section .section-label::after {
        content: '';
        flex: 1;
        height: 1px;
        background: rgba(244,196,48,0.2);
    }

    .tkt-booking-section .section-title {
        font-family: 'Bebas Neue', sans-serif;
        font-size: 52px;
        letter-spacing: 4px;
        color: #fff;
        margin: 0 0 40px;
        line-height: 1;
    }
    .tkt-booking-section .section-title span { color: #f4c430; }

    /* ── TICKET CARDS ─────────────────────────────────────── */
    .tkt-ticket-cards { display: flex; flex-direction: column; gap: 14px; }

    .tkt-ticket-card {
        background: #111;
        border: 1px solid rgba(255,255,255,0.07);
        padding: 0;
        display: flex;
        align-items: stretch;
        position: relative;
        overflow: hidden;
        transition: border-color 0.25s, transform 0.2s;
        cursor: pointer;
    }
    .tkt-ticket-card:hover,
    .tkt-ticket-card.selected {
        border-color: rgba(244,196,48,0.5);
        transform: translateX(4px);
    }
    .tkt-ticket-card.selected {
        background: rgba(244,196,48,0.04);
    }

    /* Perforation line on the left */
    .tkt-ticket-card::before {
        content: '';
        position: absolute;
        left: 78px;
        top: -8px;
        bottom: -8px;
        width: 1px;
        background: repeating-linear-gradient(
            to bottom,
            rgba(255,255,255,0.12) 0px,
            rgba(255,255,255,0.12) 6px,
            transparent 6px,
            transparent 12px
        );
        z-index: 1;
    }

    /* Gold left stripe */
    .tkt-ticket-card .card-stripe {
        width: 4px;
        background: #f4c430;
        flex-shrink: 0;
        transition: width 0.25s;
    }
    .tkt-ticket-card.selected .card-stripe,
    .tkt-ticket-card:hover .card-stripe { width: 6px; }

    /* Category badge */
    .tkt-ticket-card .card-badge {
        width: 74px;
        display: flex;
        flex-direction: column;
        align-items: center;
        justify-content: center;
        padding: 20px 10px;
        flex-shrink: 0;
        background: rgba(0,0,0,0.3);
        position: relative;
        z-index: 2;
    }
    .tkt-ticket-card .card-badge .badge-icon {
        font-size: 20px;
        color: #f4c430;
        margin-bottom: 6px;
    }
    .tkt-ticket-card .card-badge .badge-label {
        font-family: 'Bebas Neue', sans-serif;
        font-size: 11px;
        letter-spacing: 2px;
        color: rgba(255,255,255,0.5);
        text-align: center;
        line-height: 1.2;
    }

    /* Main info */
    .tkt-ticket-card .card-info {
        flex: 1;
        padding: 22px 24px;
        display: flex;
        align-items: center;
        justify-content: space-between;
        gap: 16px;
        position: relative;
        z-index: 2;
    }

    .tkt-ticket-card .card-info .ticket-name {
        font-family: 'Bebas Neue', sans-serif;
        font-size: 22px;
        letter-spacing: 2px;
        color: #fff;
        display: block;
        margin-bottom: 4px;
    }
    .tkt-ticket-card .card-info .ticket-desc {
        font-family: 'Barlow', sans-serif;
        font-size: 12px;
        color: rgba(255,255,255,0.35);
        letter-spacing: 0.3px;
    }

    /* Availability badge */
    .tkt-avail-badge {
        font-family: 'Barlow', sans-serif;
        font-size: 10px;
        font-weight: 600;
        letter-spacing: 2px;
        text-transform: uppercase;
        padding: 3px 9px;
        border-radius: 0;
        display: inline-block;
        margin-top: 6px;
    }
    .tkt-avail-badge.available   { background: rgba(39,174,96,0.15);  color: #27ae60; border: 1px solid rgba(39,174,96,0.3); }
    .tkt-avail-badge.limited     { background: rgba(231,76,60,0.12);   color: #e74c3c; border: 1px solid rgba(231,76,60,0.25); }
    .tkt-avail-badge.selling     { background: rgba(243,156,18,0.12);  color: #f39c12; border: 1px solid rgba(243,156,18,0.25); }

    /* Price block */
    .tkt-ticket-card .card-price {
        text-align: right;
        flex-shrink: 0;
    }
    .tkt-ticket-card .card-price .price-amount {
        font-family: 'Bebas Neue', sans-serif;
        font-size: 32px;
        color: #f4c430;
        letter-spacing: 2px;
        display: block;
        line-height: 1;
    }
    .tkt-ticket-card .card-price .price-label {
        font-family: 'Barlow', sans-serif;
        font-size: 10px;
        color: rgba(255,255,255,0.3);
        text-transform: uppercase;
        letter-spacing: 2px;
    }

    /* Qty counter */
    .tkt-qty-counter {
        display: flex;
        align-items: center;
        gap: 0;
        border: 1px solid rgba(255,255,255,0.12);
        flex-shrink: 0;
    }
    .tkt-qty-counter button {
        width: 36px;
        height: 36px;
        background: rgba(255,255,255,0.05);
        border: none;
        color: #fff;
        font-size: 16px;
        line-height: 1;
        cursor: pointer;
        transition: background 0.2s, color 0.2s;
        font-family: 'Barlow', sans-serif;
    }
    .tkt-qty-counter button:hover { background: #f4c430; color: #000; }
    .tkt-qty-counter .qty-val {
        width: 42px;
        height: 36px;
        background: transparent;
        border: none;
        border-left: 1px solid rgba(255,255,255,0.08);
        border-right: 1px solid rgba(255,255,255,0.08);
        color: #fff;
        font-family: 'Bebas Neue', sans-serif;
        font-size: 18px;
        letter-spacing: 1px;
        text-align: center;
        -moz-appearance: textfield;
    }
    .tkt-qty-counter .qty-val::-webkit-outer-spin-button,
    .tkt-qty-counter .qty-val::-webkit-inner-spin-button { -webkit-appearance: none; }

    /* Add to cart btn */
    .tkt-add-btn {
        font-family: 'Bebas Neue', sans-serif;
        font-size: 14px;
        letter-spacing: 2px;
        background: #f4c430;
        color: #000;
        border: none;
        padding: 0 22px;
        height: 36px;
        cursor: pointer;
        transition: background 0.2s, transform 0.15s;
        flex-shrink: 0;
        white-space: nowrap;
    }
    .tkt-add-btn:hover { background: #e0b020; transform: scale(1.02); }
    .tkt-add-btn:active { transform: scale(0.98); }

    /* ── ORDER SUMMARY SIDEBAR ────────────────────────────── */
    .tkt-summary-box {
        background: #111;
        border: 1px solid rgba(244,196,48,0.2);
        position: sticky;
        top: 100px;
    }

    .tkt-summary-box .summary-header {
        background: #f4c430;
        padding: 16px 24px;
        display: flex;
        align-items: center;
        gap: 10px;
    }
    .tkt-summary-box .summary-header span {
        font-family: 'Bebas Neue', sans-serif;
        font-size: 18px;
        letter-spacing: 3px;
        color: #000;
    }
    .tkt-summary-box .summary-header i {
        font-size: 16px;
        color: #000;
    }

    .tkt-summary-box .summary-body { padding: 24px; }

    .tkt-summary-box .summary-empty {
        text-align: center;
        padding: 30px 0;
    }
    .tkt-summary-box .summary-empty i {
        font-size: 36px;
        color: rgba(255,255,255,0.1);
        display: block;
        margin-bottom: 10px;
    }
    .tkt-summary-box .summary-empty p {
        font-family: 'Barlow', sans-serif;
        font-size: 12px;
        color: rgba(255,255,255,0.25);
        letter-spacing: 1px;
        text-transform: uppercase;
        margin: 0;
    }

    .tkt-summary-box .summary-items { display: none; }
    .tkt-summary-box.has-items .summary-empty { display: none; }
    .tkt-summary-box.has-items .summary-items { display: block; }

    .summary-item {
        display: flex;
        justify-content: space-between;
        align-items: center;
        padding: 10px 0;
        border-bottom: 1px solid rgba(255,255,255,0.06);
    }
    .summary-item:last-child { border-bottom: none; }
    .summary-item .si-left .si-name {
        font-family: 'Bebas Neue', sans-serif;
        font-size: 15px;
        letter-spacing: 1px;
        color: #fff;
        display: block;
    }
    .summary-item .si-left .si-qty {
        font-family: 'Barlow', sans-serif;
        font-size: 11px;
        color: rgba(255,255,255,0.35);
    }
    .summary-item .si-right {
        font-family: 'Bebas Neue', sans-serif;
        font-size: 16px;
        letter-spacing: 1px;
        color: #f4c430;
    }
    .summary-item .si-remove {
        background: none;
        border: none;
        color: rgba(255,255,255,0.2);
        font-size: 12px;
        cursor: pointer;
        padding: 0 6px;
        transition: color 0.2s;
    }
    .summary-item .si-remove:hover { color: #e74c3c; }

    .summary-divider {
        border: none;
        border-top: 1px solid rgba(255,255,255,0.08);
        margin: 16px 0;
    }

    .summary-total-row {
        display: flex;
        justify-content: space-between;
        align-items: baseline;
        margin-bottom: 8px;
    }
    .summary-total-row .tot-label {
        font-family: 'Barlow', sans-serif;
        font-size: 11px;
        letter-spacing: 3px;
        text-transform: uppercase;
        color: rgba(255,255,255,0.4);
    }
    .summary-total-row .tot-val {
        font-family: 'Bebas Neue', sans-serif;
        font-size: 28px;
        letter-spacing: 2px;
        color: #f4c430;
    }

    .tkt-checkout-btn {
        display: block;
        width: 100%;
        background: #f4c430;
        color: #000;
        font-family: 'Bebas Neue', sans-serif;
        font-size: 17px;
        letter-spacing: 4px;
        text-align: center;
        padding: 15px;
        border: none;
        cursor: pointer;
        text-decoration: none;
        margin-top: 18px;
        transition: background 0.2s, transform 0.15s;
    }
    .tkt-checkout-btn:hover {
        background: #e0b020;
        color: #000;
        text-decoration: none;
        transform: translateY(-1px);
    }
    .tkt-checkout-btn:disabled {
        background: rgba(244,196,48,0.2);
        color: rgba(0,0,0,0.4);
        cursor: not-allowed;
        transform: none;
    }

    .summary-note {
        font-family: 'Barlow', sans-serif;
        font-size: 11px;
        color: rgba(255,255,255,0.2);
        text-align: center;
        margin-top: 14px;
        line-height: 1.5;
    }
    .summary-note i { color: #27ae60; margin-right: 4px; }

    /* Toast notification */
    .tkt-toast {
        position: fixed;
        bottom: 30px;
        right: 30px;
        background: #f4c430;
        color: #000;
        font-family: 'Bebas Neue', sans-serif;
        font-size: 14px;
        letter-spacing: 2px;
        padding: 14px 24px;
        z-index: 9999;
        transform: translateY(80px);
        opacity: 0;
        transition: transform 0.3s ease, opacity 0.3s ease;
        pointer-events: none;
        display: flex;
        align-items: center;
        gap: 10px;
    }
    .tkt-toast.show { transform: translateY(0); opacity: 1; }

    /* ── HOUSE RULES ──────────────────────────────────────── */
    .tkt-house-rules {
        background: #0d0d0d;
        border: 1px solid rgba(255,255,255,0.06);
        padding: 30px;
        margin-top: 50px;
    }

    .tkt-house-rules .rules-header {
        display: flex;
        align-items: center;
        gap: 12px;
        margin-bottom: 20px;
    }
    .tkt-house-rules .rules-header i {
        font-size: 18px;
        color: #f4c430;
    }
    .tkt-house-rules .rules-header h4 {
        font-family: 'Bebas Neue', sans-serif;
        font-size: 22px;
        letter-spacing: 3px;
        color: #fff;
        margin: 0;
    }

    .tkt-rules-grid {
        display: grid;
        grid-template-columns: repeat(2, 1fr);
        gap: 10px;
    }
    @media (max-width: 768px) { .tkt-rules-grid { grid-template-columns: 1fr; } }

    .tkt-rules-grid .rule-item {
        display: flex;
        align-items: flex-start;
        gap: 10px;
        padding: 10px 14px;
        background: rgba(255,255,255,0.03);
        border-left: 2px solid rgba(244,196,48,0.25);
    }
    .tkt-rules-grid .rule-item::before {
        content: '—';
        color: #f4c430;
        font-family: 'Bebas Neue', sans-serif;
        font-size: 14px;
        flex-shrink: 0;
        margin-top: 1px;
    }
    .tkt-rules-grid .rule-item span {
        font-family: 'Barlow', sans-serif;
        font-size: 13px;
        color: rgba(255,255,255,0.5);
        line-height: 1.5;
    }
</style>

<!-- Sub Banner -->
<div class="sub-banner">
    <div class="container">
        <h6>{{ $event->name }}</h6>
        <p>Secure your spot — limited tickets available</p>
    </div>
</div>

<!-- Main Content -->
<section class="kode_content_wrap">
    <div class="container">
        <div class="row">
            <div class="col-md-12">

                <!-- Event Hero Image + Countdown -->
                <div class="kode_event_counter_section">
                    <figure>
                        <img src="{{ $event->cover_image_url ?? asset('extra-images/event-update1.jpg') }}" alt="{{ $event->name }}">
                        <ul class="countdown">
                            <li><span class="days">72</span><p class="days_ref">days</p></li>
                            <li><span class="hours">13</span><p class="hours_ref">hours</p></li>
                            <li><span class="minutes">24</span><p class="minutes_ref">minute</p></li>
                            <li><span class="seconds last">00</span><p class="seconds_ref">sec</p></li>
                        </ul>
                    </figure>
                </div>

                <!-- Event Meta -->
                <div class="kode_event_conter_capstion">
                    <div class="counter-meta">
                        <ul class="info-event">
                            <li><i class="fa fa-calendar"></i><a href="#"><span>Date: {{ $event->event_date->format('d F Y') }}</span></a></li>
                            <li><i class="fa fa-map-marker"></i><a href="#"><span>Location: {{ $event->location }}</span></a></li>
                            <li><i class="fa fa-clock-o"></i><a href="#"><span>Time: {{ \Carbon\Carbon::parse($event->event_time)->format('g:i A') }}</span></a></li>
                        </ul>
                    </div>
                    <img style="height:500px; object-fit:contain;" src="{{ $event->cover_image_url ?? asset('extra-images/event-update1.jpg') }}" alt="{{ $event->name }}">
                </div>

                <!-- Event Visuals -->
                <div class="other-events">
                    <div class="row">
                        <div class="col-md-6 col-sm-6 col-xs-12">
                            <div class="kode-event-place-holder">
                                <figure>
                                    <img src="{{ $event->images->first()?->path_url ?? $event->cover_image_url ?? asset('extra-images/event-n1.jpg') }}" alt="{{ $event->name }}">
                                    <div class="event-frame-over">
                                        <h2>EVENT DETAILS</h2>
                                        <ul>
                                            <li><h3>Start Date:</h3><span>{{ $event->event_date->format('d-m-y') }}</span></li>
                                            <li><h3>Status:</h3><span>{{ str($event->status)->replace('_', ' ')->title() }}</span></li>
                                            <li><h3>Location:</h3><span>{{ $event->location }}</span></li>
                                        </ul>
                                    </div>
                                </figure>
                            </div>
                        </div>
                        <div class="col-md-6 col-sm-6 col-xs-12">
                            <div class="kode-event-place-holder">
                                <figure>
                                    <img src="{{ $event->images->skip(1)->first()?->path_url ?? $event->cover_image_url ?? asset('extra-images/event-n2.jpg') }}" alt="{{ $event->name }}">
                                    <div class="event-frame-over">
                                        <h2>EVENT DETAILS</h2>
                                        <ul>
                                            <li><h3>Doors Open:</h3><span>{{ \Carbon\Carbon::parse($event->event_time)->format('g:i A') }}</span></li>
                                            <li><h3>Map:</h3><span>{{ $event->map_url ? 'Available' : 'Not Provided' }}</span></li>
                                            <li><h3>Venue:</h3><span>{{ $event->location }}</span></li>
                                        </ul>
                                    </div>
                                </figure>
                            </div>
                        </div>

                        <div class="col-md-12">
                            <div class="kode-event-place-holder-capstion">
                                {!! nl2br(e($event->description)) !!}
                            </div>
                        </div>
                    </div>
                </div>

            </div>
        </div>
    </div>
</section>

<!-- ═══════════════════════════════════════════════════════════ -->
<!--  TICKET BOOKING SECTION                                      -->
<!-- ═══════════════════════════════════════════════════════════ -->
<div class="tkt-booking-section">
    <div class="container">

        <div class="section-label"><span>Secure Your Spot</span></div>
        <h2 class="section-title">BUY <span>TICKETS</span></h2>

        <div class="row">

            <!-- LEFT: Ticket Cards -->
            <div class="col-md-8 col-sm-12">
                <div class="tkt-ticket-cards">
                    @forelse($event->tickets as $ticket)
                        @php
                            $badgeType = $loop->first ? 'limited' : 'available';
                        @endphp
                        <div class="tkt-ticket-card" data-ticket="{{ $ticket->name }}" data-price="{{ number_format($ticket->price, 2, '.', '') }}" data-id="ticket-{{ $ticket->id }}">
                            <div class="card-stripe"></div>
                            <div class="card-badge">
                                <i class="fa fa-ticket card-badge-icon" style="font-size:20px;color:#f4c430;margin-bottom:6px;"></i>
                                <span class="badge-label">{{ strtoupper(substr($ticket->label ?: $ticket->name, 0, 6)) }}</span>
                            </div>
                            <div class="card-info">
                                <div class="card-meta">
                                    <span class="ticket-name">{{ strtoupper($ticket->name) }}</span>
                                    <span class="ticket-desc">{{ $ticket->description ?: 'General admission ticket' }}</span>
                                    <span class="tkt-avail-badge {{ $badgeType }}">{{ str($ticket->status)->replace('_', ' ')->title() }}</span>
                                </div>
                                <div class="card-price">
                                    <span class="price-amount">${{ number_format($ticket->price, 2) }}</span>
                                    <span class="price-label">per ticket</span>
                                </div>
                                <div class="tkt-qty-counter">
                                    <button class="qty-btn qty-minus" type="button">−</button>
                                    <input class="qty-val" type="text" value="1" readonly>
                                    <button class="qty-btn qty-plus" type="button">+</button>
                                </div>
                                <button class="tkt-add-btn" type="button">
                                    <i class="fa fa-plus" style="margin-right:6px;"></i>ADD
                                </button>
                            </div>
                        </div>
                    @empty
                        <div class="tkt-ticket-empty">
                            <div class="card-stripe"></div>
                            <div class="card-badge">
                                <i class="fa fa-exclamation-circle card-badge-icon" style="font-size:20px;color:#f4c430;margin-bottom:6px;"></i>
                                <span class="badge-label">INFO</span>
                            </div>
                            <div class="card-info">
                                <div class="card-meta">
                                    <span class="ticket-name">TICKETS NOT AVAILABLE YET</span>
                                    <span class="ticket-desc">Please check again later.</span>
                                </div>
                            </div>
                        </div>
                    @endforelse

                </div><!-- /tkt-ticket-cards -->

                <!-- House Rules -->
                <div class="tkt-house-rules">
                    <div class="rules-header">
                        <i class="fa fa-exclamation-triangle"></i>
                        <h4>HOUSE RULES</h4>
                    </div>
                    <div class="tkt-rules-grid">
                        @php
                            $rules = collect(preg_split('/\r\n|\r|\n/', (string) $event->house_rules))
                                ->map(fn ($rule) => trim($rule))
                                ->filter();
                        @endphp
                        @forelse($rules as $rule)
                            <div class="rule-item"><span>{{ $rule }}</span></div>
                        @empty
                            <div class="rule-item"><span>Follow venue instructions and security guidelines during the event.</span></div>
                            <div class="rule-item"><span>Please arrive early to complete the entry process smoothly.</span></div>
                        @endforelse
                    </div>
                </div>

            </div><!-- /col-md-8 -->

            <!-- RIGHT: Order Summary -->
            <div class="col-md-4 col-sm-12">
                <div class="tkt-summary-box" id="orderSummary">
                    <div class="summary-header">
                        <i class="fa fa-shopping-cart"></i>
                        <span>ORDER SUMMARY</span>
                    </div>
                    <div class="summary-body">

                        <!-- Empty state -->
                        <div class="summary-empty">
                            <i class="fa fa-ticket"></i>
                            <p>No tickets selected yet</p>
                        </div>

                        <!-- Items list -->
                        <div class="summary-items" id="summaryItems"></div>

                        <hr class="summary-divider">

                        <div class="summary-total-row">
                            <span class="tot-label">Total</span>
                            <span class="tot-val" id="summaryTotal">$0.00</span>
                        </div>

                        <a href="{{ url('/checkout') }}" class="tkt-checkout-btn" id="checkoutBtn" style="pointer-events:none; background:rgba(244,196,48,0.2); color:rgba(0,0,0,0.4);">
                            PROCEED TO CHECKOUT &nbsp;<i class="fa fa-arrow-right"></i>
                        </a>

                        <p class="summary-note">
                            <i class="fa fa-lock"></i>Secure checkout · No hidden fees
                        </p>
                    </div>
                </div>
            </div><!-- /col-md-4 -->

        </div><!-- /row -->
    </div><!-- /container -->
</div>

<!-- Toast -->
<div class="tkt-toast" id="tktToast">
    <i class="fa fa-check"></i>
    <span id="toastMsg">Ticket added to order!</span>
</div>

<script>
(function() {
    // Cart state
    var cart = {};

    // Qty counters
    document.querySelectorAll('.tkt-ticket-card').forEach(function(card) {
        var minus = card.querySelector('.qty-minus');
        var plus  = card.querySelector('.qty-plus');
        var val   = card.querySelector('.qty-val');
        var addBtn = card.querySelector('.tkt-add-btn');

        if (!minus || !plus || !val || !addBtn) {
            return;
        }

        minus.addEventListener('click', function(e) {
            e.stopPropagation();
            var v = parseInt(val.value);
            if (v > 1) val.value = v - 1;
        });
        plus.addEventListener('click', function(e) {
            e.stopPropagation();
            var v = parseInt(val.value);
            if (v < 10) val.value = v + 1;
        });

        // Add to cart
        addBtn.addEventListener('click', function(e) {
            e.stopPropagation();
            var id     = card.dataset.id;
            var name   = card.dataset.ticket;
            var price  = parseFloat(card.dataset.price);
            var qty    = parseInt(val.value);

            if (cart[id]) {
                cart[id].qty += qty;
            } else {
                cart[id] = { name: name, price: price, qty: qty };
            }

            card.classList.add('selected');
            renderSummary();
            showToast(name + ' × ' + qty + ' added!');
        });
    });

    function renderSummary() {
        var summaryBox   = document.getElementById('orderSummary');
        var summaryItems = document.getElementById('summaryItems');
        var summaryTotal = document.getElementById('summaryTotal');
        var checkoutBtn  = document.getElementById('checkoutBtn');

        var keys = Object.keys(cart);
        var total = 0;
        summaryItems.innerHTML = '';

        keys.forEach(function(id) {
            var item = cart[id];
            var sub  = item.price * item.qty;
            total   += sub;

            var div = document.createElement('div');
            div.className = 'summary-item';
            div.innerHTML =
                '<div class="si-left">' +
                    '<span class="si-name">' + item.name.toUpperCase() + '</span>' +
                    '<span class="si-qty">× ' + item.qty + ' ticket' + (item.qty > 1 ? 's' : '') + '</span>' +
                '</div>' +
                '<div style="display:flex;align-items:center;gap:8px;">' +
                    '<span class="si-right">$' + sub.toFixed(2) + '</span>' +
                    '<button class="si-remove" data-id="' + id + '" title="Remove">✕</button>' +
                '</div>';
            summaryItems.appendChild(div);
        });

        // Remove buttons
        summaryItems.querySelectorAll('.si-remove').forEach(function(btn) {
            btn.addEventListener('click', function() {
                var rid = this.dataset.id;
                // Deselect card
                var card = document.querySelector('[data-id="' + rid + '"]');
                if (card) card.classList.remove('selected');
                delete cart[rid];
                renderSummary();
            });
        });

        summaryTotal.textContent = '$' + total.toFixed(2);

        if (keys.length > 0) {
            summaryBox.classList.add('has-items');
            checkoutBtn.style.pointerEvents = 'auto';
            checkoutBtn.style.background = '#f4c430';
            checkoutBtn.style.color = '#000';
        } else {
            summaryBox.classList.remove('has-items');
            checkoutBtn.style.pointerEvents = 'none';
            checkoutBtn.style.background = 'rgba(244,196,48,0.2)';
            checkoutBtn.style.color = 'rgba(0,0,0,0.4)';
        }
    }

    function showToast(msg) {
        var toast   = document.getElementById('tktToast');
        var toastMsg = document.getElementById('toastMsg');
        toastMsg.textContent = msg;
        toast.classList.add('show');
        setTimeout(function() { toast.classList.remove('show'); }, 2800);
    }
})();
</script>

@endsection
