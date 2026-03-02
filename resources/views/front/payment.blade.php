@extends('front.layout.master')

@section('content')
@php
    $methods  = collect($activePaymentMethods ?? []);
    $selected = old('payment_method', $selectedMethod ?? $order->payment_method);
@endphp

<style>
@import url('https://fonts.googleapis.com/css2?family=Syne:wght@600;700;800&family=DM+Sans:wght@300;400;500&display=swap');

:root {
    --bg:       #060608;
    --surface:  #0e0e12;
    --surface2: #16161d;
    --border:   rgba(255,255,255,0.07);
    --gold:     #f5b800;
    --gold-d:   #c99300;
    --text:     #e8e8ef;
    --muted:    #6b6b7e;
    --red:      #e8445a;
    --green:    #22c55e;
    --radius:   14px;
    --fh:       'Syne', sans-serif;
    --fb:       'DM Sans', sans-serif;
}

/* ── Page ── */
.pm-page {
    background: var(--bg);
    min-height: 100vh;
    font-family: var(--fb);
    color: var(--text);
    padding-bottom: 80px;
}

/* ── Banner ── */
.pm-banner {
    background: linear-gradient(135deg,#060608 0%,#0e0e12 60%,#0d0b00 100%);
    border-bottom: 1px solid rgba(245,184,0,.12);
    padding: 36px 0 28px;
    position: relative; overflow: hidden;
}
.pm-banner::before {
    content:''; position:absolute; top:-60px; right:-60px;
    width:280px; height:280px;
    background: radial-gradient(circle,rgba(245,184,0,.07) 0%,transparent 70%);
    pointer-events:none;
}
.pm-banner-inner { position:relative; z-index:1; }
.pm-banner-label {
    font-family:var(--fh); font-size:10px; font-weight:700;
    letter-spacing:3px; text-transform:uppercase;
    color:var(--gold); margin-bottom:8px;
    display:flex; align-items:center; gap:8px;
}
.pm-banner-label::before { content:''; width:20px; height:2px; background:var(--gold); border-radius:2px; }
.pm-banner-title { font-family:var(--fh); font-size:24px; font-weight:800; color:#fff; letter-spacing:-.5px; margin:0; }
.pm-banner-title span { color:var(--gold); }
.pm-banner-sub { margin:4px 0 0; color:var(--muted); font-size:13px; font-family:monospace; letter-spacing:.5px; }

/* ── Layout ── */
.pm-wrap {
    display: grid;
    grid-template-columns: 1fr 360px;
    gap: 24px;
    padding: 40px 0 0;
    align-items: start;
}
@media(max-width:900px){ .pm-wrap { grid-template-columns:1fr; } .pm-summary { order:-1; } }

/* ── Card ── */
.pm-card {
    background: var(--surface);
    border: 1px solid var(--border);
    border-radius: var(--radius);
    overflow: hidden;
}
.pm-card-head {
    display: flex; align-items: center; gap: 10px;
    padding: 16px 22px;
    border-bottom: 1px solid var(--border);
    background: var(--surface2);
}
.pm-card-head-icon {
    width: 32px; height: 32px; border-radius: 8px;
    background: rgba(245,184,0,.1); border: 1px solid rgba(245,184,0,.2);
    color: var(--gold); display: flex; align-items: center; justify-content: center;
    font-size: 13px; flex-shrink: 0;
}
.pm-card-title {
    font-family: var(--fh); font-size: 11px; font-weight: 700;
    letter-spacing: 2px; text-transform: uppercase; color: var(--gold);
}
.pm-card-body { padding: 22px; }

/* ── Payment methods ── */
.pm-methods { display: flex; flex-direction: column; gap: 10px; }

.pm-method {
    display: flex; align-items: center; gap: 14px;
    background: var(--surface2);
    border: 1px solid var(--border);
    border-radius: 10px; padding: 16px 18px;
    cursor: pointer;
    transition: border-color .2s, background .2s, transform .15s;
    position: relative; overflow: hidden;
}
.pm-method::before {
    content: '';
    position: absolute; left: 0; top: 0; bottom: 0;
    width: 3px; background: transparent;
    transition: background .2s;
}
.pm-method:hover { border-color: rgba(245,184,0,.25); background: rgba(245,184,0,.03); }
.pm-method.active {
    border-color: rgba(245,184,0,.45);
    background: rgba(245,184,0,.06);
}
.pm-method.active::before { background: var(--gold); }

/* Custom radio */
.pm-radio {
    width: 18px; height: 18px; border-radius: 50%;
    border: 2px solid var(--border);
    flex-shrink: 0; display: flex; align-items: center; justify-content: center;
    transition: border-color .2s;
}
.pm-method.active .pm-radio { border-color: var(--gold); }
.pm-radio-dot {
    width: 8px; height: 8px; border-radius: 50%;
    background: var(--gold); opacity: 0;
    transition: opacity .2s, transform .2s;
    transform: scale(0.5);
}
.pm-method.active .pm-radio-dot { opacity: 1; transform: scale(1); }

/* Method icon */
.pm-method-icon {
    width: 40px; height: 40px; border-radius: 8px;
    background: rgba(255,255,255,.04); border: 1px solid var(--border);
    display: flex; align-items: center; justify-content: center;
    font-size: 18px; flex-shrink: 0;
}

.pm-method-info { flex: 1; }
.pm-method-name {
    font-family: var(--fh); font-size: 13px; font-weight: 700;
    color: #fff; margin-bottom: 3px;
    transition: color .2s;
}
.pm-method.active .pm-method-name { color: var(--gold); }
.pm-method-desc { font-size: 12px; color: var(--muted); line-height: 1.4; }

/* Hidden native radio */
.pm-method input[type=radio] { display: none; }

/* ── Pay button ── */
.pm-pay-btn {
    display: flex; align-items: center; justify-content: center; gap: 10px;
    width: 100%; background: var(--gold); color: #000;
    font-family: var(--fb); font-size: 15px; font-weight: 700;
    letter-spacing: .3px; text-transform: uppercase;
    border: none; border-radius: 10px;
    padding: 15px 24px; cursor: pointer; margin-top: 18px;
    transition: background .2s, transform .1s;
    font-variant-numeric: tabular-nums; font-feature-settings: "tnum";
}
.pm-pay-btn:hover { background: #ffc820; }
.pm-pay-btn:active { transform: scale(.99); }
.pm-pay-btn:disabled {
    background: rgba(245,184,0,.2); color: rgba(0,0,0,.4);
    cursor: not-allowed; transform: none;
}
.pm-pay-btn svg { width: 16px; height: 16px; }

/* Secure note */
.pm-secure {
    display: flex; align-items: center; justify-content: center; gap: 7px;
    margin-top: 12px; font-size: 11px; color: var(--muted);
    font-family: var(--fb);
}
.pm-secure i { color: var(--green); font-size: 11px; }

/* ── Alert ── */
.pm-alert {
    display: flex; align-items: flex-start; gap: 10px;
    background: rgba(232,68,90,.08); border: 1px solid rgba(232,68,90,.3);
    border-radius: 10px; padding: 13px 16px; margin-bottom: 18px;
    font-size: 13px; color: #f0849a;
}
.pm-alert i { flex-shrink: 0; margin-top: 1px; }

/* ── Summary card ── */
.pm-summary-items { margin-bottom: 4px; }
.pm-summary-item {
    display: flex; justify-content: space-between; align-items: flex-start;
    gap: 12px; padding: 11px 0;
    border-bottom: 1px solid rgba(255,255,255,.04);
    font-size: 13px;
}
.pm-summary-item:last-of-type { border-bottom: none; }
.pm-summary-item-name { color: var(--text); flex: 1; line-height: 1.4; }
.pm-summary-item-qty { font-size: 11px; color: var(--muted); margin-top: 2px; }
.pm-summary-item-price { color: var(--gold); font-weight: 600; white-space: nowrap; font-size: 14px; }

.pm-summary-divider { height: 1px; background: var(--border); margin: 4px 0 14px; }
.pm-summary-total {
    display: flex; justify-content: space-between; align-items: baseline;
    gap: 12px;
}
.pm-summary-total-label { font-family: var(--fh); font-size: 11px; letter-spacing: 2px; text-transform: uppercase; color: var(--muted); }
.pm-summary-total-val {
    font-family: var(--fb); font-size: 20px; font-weight: 700;
    color: var(--gold); letter-spacing: 0;
    font-variant-numeric: tabular-nums; font-feature-settings: "tnum";
}

/* Order status pill */
.pm-status-pill {
    display: inline-flex; align-items: center; gap: 6px;
    margin-top: 14px;
    font-family: var(--fh); font-size: 10px; font-weight: 700;
    letter-spacing: .5px; text-transform: uppercase;
    padding: 5px 12px; border-radius: 99px;
}
.pm-status-pending  { color: var(--gold);  background: rgba(245,184,0,.1);  border: 1px solid rgba(245,184,0,.25); }
.pm-status-paid     { color: var(--green); background: rgba(34,197,94,.1);  border: 1px solid rgba(34,197,94,.25); }
.pm-status-rejected { color: var(--red);   background: rgba(232,68,90,.1);  border: 1px solid rgba(232,68,90,.25); }
</style>

{{-- Banner --}}
<div class="pm-banner">
    <div class="container pm-banner-inner">
        <div class="pm-banner-label">Checkout</div>
        <h1 class="pm-banner-title">Complete Your <span>Payment</span></h1>
        <p class="pm-banner-sub">Order #{{ $order->order_number }}</p>
    </div>
</div>

<section class="pm-page">
    <div class="container">
        <div class="pm-wrap">

            {{-- ── LEFT: Payment methods ── --}}
            <div>
                <div class="pm-card">
                    <div class="pm-card-head">
                        <div class="pm-card-head-icon"><i class="fa fa-credit-card"></i></div>
                        <div class="pm-card-title">Select Payment Method</div>
                    </div>
                    <div class="pm-card-body">

                        @if($errors->has('payment') || $errors->has('payment_method'))
                            <div class="pm-alert">
                                <i class="fa fa-circle-exclamation"></i>
                                {{ $errors->first('payment') ?: $errors->first('payment_method') }}
                            </div>
                        @endif

                        <input type="hidden" id="selectedPaymentMethod" value="{{ $selected }}">

                        <div class="pm-methods" id="paymentSelection">
                            @forelse($methods as $method)
                                @php
                                    $methodCode = (string) $method->code;
                                    $isSelected = $selected === $methodCode;
                                    $icons = [
                                        'card'   => '💳',
                                        'visa'   => '💳',
                                        'wallet' => '👛',
                                        'cash'   => '💵',
                                        'bank'   => '🏦',
                                        'fawry'  => '🔵',
                                    ];
                                    $icon = $icons[strtolower($methodCode)] ?? '💳';
                                @endphp
                                <label class="pm-method {{ $isSelected ? 'active' : '' }}"
                                       data-code="{{ $methodCode }}"
                                       data-provider="{{ $method->provider }}">
                                    <input type="radio" name="payment_method_radio"
                                           value="{{ $methodCode }}" {{ $isSelected ? 'checked' : '' }}>
                                    <div class="pm-radio">
                                        <div class="pm-radio-dot"></div>
                                    </div>
                                    <div class="pm-method-icon">{{ $icon }}</div>
                                    <div class="pm-method-info">
                                        <div class="pm-method-name">{{ $method->checkout_label ?: $method->name }}</div>
                                        <div class="pm-method-desc">{{ $method->checkout_description ?: 'Pay securely with '.$method->name }}</div>
                                    </div>
                                </label>
                            @empty
                                <div class="pm-alert">
                                    <i class="fa fa-circle-exclamation"></i>
                                    No active payment methods available right now.
                                </div>
                            @endforelse
                        </div>

                        <button type="button" id="paymobBtn" class="pm-pay-btn">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5">
                                <rect x="1" y="4" width="22" height="16" rx="2"/><line x1="1" y1="10" x2="23" y2="10"/>
                            </svg>
                            Pay {{ number_format($order->total_amount, 2) }} EGP
                        </button>

                        <div class="pm-secure">
                            <i class="fa fa-lock"></i>
                            Secured & encrypted · No hidden fees
                        </div>

                    </div>
                </div>
            </div>

            {{-- ── RIGHT: Order summary ── --}}
            <div class="pm-summary">
                <div class="pm-card">
                    <div class="pm-card-head">
                        <div class="pm-card-head-icon"><i class="fa fa-receipt"></i></div>
                        <div class="pm-card-title">Order Summary</div>
                    </div>
                    <div class="pm-card-body">

                        <div class="pm-summary-items">
                            @foreach($order->items as $item)
                                <div class="pm-summary-item">
                                    <div>
                                        <div class="pm-summary-item-name">{{ $item->ticket_name }}</div>
                                        <div class="pm-summary-item-qty">× {{ $item->quantity }} ticket{{ $item->quantity > 1 ? 's' : '' }}</div>
                                    </div>
                                    <div class="pm-summary-item-price">{{ number_format($item->line_total, 2) }} EGP</div>
                                </div>
                            @endforeach
                        </div>

                        <div class="pm-summary-divider"></div>

                        <div class="pm-summary-total">
                            <span class="pm-summary-total-label">Total</span>
                            <span class="pm-summary-total-val">{{ number_format($order->total_amount, 2) }} <span style="font-size:14px;color:var(--muted);font-family:var(--fb);font-weight:400;">EGP</span></span>
                        </div>

                        @php
                            $statusClass = match($order->status) {
                                'paid'                 => 'pm-status-paid',
                                'rejected','canceled'  => 'pm-status-rejected',
                                default                => 'pm-status-pending',
                            };
                        @endphp
                        <div>
                            <span class="pm-status-pill {{ $statusClass }}">
                                ● {{ ucwords(str_replace('_', ' ', $order->status)) }}
                            </span>
                        </div>

                    </div>
                </div>
            </div>

        </div>
    </div>
</section>

<script>
(() => {
    const methodCards = Array.from(document.querySelectorAll('.pm-method'));
    const hidden      = document.getElementById('selectedPaymentMethod');
    const paymobBtn   = document.getElementById('paymobBtn');

    function selectCard(code) {
        hidden.value = code;
        methodCards.forEach(card => {
            const isActive = card.dataset.code === code;
            card.classList.toggle('active', isActive);
            const radio = card.querySelector('input[type="radio"]');
            if (radio) radio.checked = isActive;
        });
    }

    methodCards.forEach(card => card.addEventListener('click', () => selectCard(card.dataset.code)));

    paymobBtn?.addEventListener('click', () => {
        const active = methodCards.find(c => c.classList.contains('active'));
        const code   = active?.dataset.code || hidden.value;
        if (!code) return;
        window.location.href = `{{ route('front.orders.payment.paymob', ['order' => $order, 'token' => $order->payment_link_token]) }}?method=${encodeURIComponent(code)}`;
    });

    // Disable pay btn if no method selected and none pre-selected
    if (!hidden.value && methodCards.length > 0) {
        paymobBtn.disabled = true;
        methodCards.forEach(card => card.addEventListener('click', () => { paymobBtn.disabled = false; }, { once: true }));
    }
})();
</script>

@endsection
