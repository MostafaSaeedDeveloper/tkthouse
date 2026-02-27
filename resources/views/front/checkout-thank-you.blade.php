@extends('front.layout.master')

@section('content')

<style>
@import url('https://fonts.googleapis.com/css2?family=Syne:wght@600;700;800&family=DM+Sans:wght@300;400;500&display=swap');

:root {
    --bg:        #060608;
    --surface:   #0e0e12;
    --surface2:  #16161d;
    --border:    rgba(255,255,255,0.07);
    --border-h:  rgba(255,185,0,0.45);
    --gold:      #f5b800;
    --gold-dim:  #c99300;
    --text:      #e8e8ef;
    --muted:     #6b6b7e;
    --green:     #22c55e;
    --radius:    12px;
    --font-head: 'Syne', sans-serif;
    --font-body: 'DM Sans', sans-serif;
}

.ty-page { background: var(--bg); min-height: 100vh; font-family: var(--font-body); color: var(--text); padding-bottom: 80px; }

/* Banner */
.co-banner {
    background: linear-gradient(135deg, #0b0b10 0%, #12120a 100%);
    border-bottom: 1px solid var(--border);
    padding: 28px 0 24px;
    position: relative; overflow: hidden;
}
.co-banner::before {
    content: ''; position: absolute; inset: 0;
    background: radial-gradient(ellipse 60% 80% at 15% 50%, rgba(245,184,0,0.06) 0%, transparent 70%);
    pointer-events: none;
}
.co-breadcrumb { font-family: var(--font-head); font-size: 11px; letter-spacing: 3px; text-transform: uppercase; color: var(--muted); margin-bottom: 6px; }
.co-banner h1 { font-family: var(--font-head); font-size: clamp(22px, 4vw, 36px); font-weight: 800; color: #fff; margin: 0; letter-spacing: -0.5px; }
.co-banner h1 span { color: var(--gold); }

/* Center layout */
.ty-layout { display: flex; justify-content: center; padding: 60px 0 80px; }
.ty-box { width: 100%; max-width: 580px; }

/* Icon ring */
.ty-icon-wrap {
    display: flex; align-items: center; justify-content: center;
    margin-bottom: 32px;
}
.ty-icon-ring {
    width: 88px; height: 88px; border-radius: 50%;
    background: rgba(245,184,0,0.08);
    border: 2px solid rgba(245,184,0,0.25);
    display: flex; align-items: center; justify-content: center;
    font-size: 38px;
    box-shadow: 0 0 40px rgba(245,184,0,0.12);
    animation: pulse-ring 2.5s ease-in-out infinite;
}
@keyframes pulse-ring {
    0%, 100% { box-shadow: 0 0 30px rgba(245,184,0,0.10); }
    50%       { box-shadow: 0 0 60px rgba(245,184,0,0.22); }
}

/* Card */
.co-card { background: var(--surface); border: 1px solid var(--border); border-radius: var(--radius); padding: 36px 32px; margin-bottom: 20px; }

/* Section label */
.co-label { display: flex; align-items: center; gap: 10px; font-family: var(--font-head); font-size: 11px; letter-spacing: 2.5px; text-transform: uppercase; color: var(--gold); margin-bottom: 18px; }
.co-label::after { content: ''; flex: 1; height: 1px; background: var(--border); }

/* Heading */
.ty-heading { font-family: var(--font-head); font-size: clamp(24px, 4vw, 34px); font-weight: 800; color: #fff; text-align: center; margin: 0 0 12px; letter-spacing: -0.5px; }
.ty-heading span { color: var(--gold); }
.ty-sub { font-size: 14px; color: var(--muted); text-align: center; line-height: 1.7; margin-bottom: 0; }

/* Divider */
.ty-divider { height: 1px; background: var(--border); margin: 28px 0; }

/* Status row */
.ty-status-row { display: flex; align-items: center; gap: 14px; background: var(--surface2); border: 1px solid var(--border); border-radius: 10px; padding: 16px 20px; }
.ty-status-dot { width: 10px; height: 10px; border-radius: 50%; background: var(--gold); flex-shrink: 0; box-shadow: 0 0 8px rgba(245,184,0,0.5); animation: blink 1.8s ease-in-out infinite; }
@keyframes blink { 0%,100%{opacity:1} 50%{opacity:0.3} }
.ty-status-text { font-size: 13px; color: var(--text); line-height: 1.5; }
.ty-status-text strong { font-family: var(--font-head); font-size: 12px; letter-spacing: 0.5px; color: var(--gold); display: block; margin-bottom: 2px; }

/* Steps */
.ty-steps { display: flex; flex-direction: column; gap: 14px; }
.ty-step { display: flex; align-items: flex-start; gap: 14px; }
.ty-step-num { width: 28px; height: 28px; border-radius: 50%; background: rgba(245,184,0,0.1); border: 1px solid rgba(245,184,0,0.3); font-family: var(--font-head); font-size: 11px; font-weight: 700; color: var(--gold); display: flex; align-items: center; justify-content: center; flex-shrink: 0; margin-top: 2px; }
.ty-step-body strong { display: block; font-family: var(--font-head); font-size: 13px; color: #fff; margin-bottom: 3px; }
.ty-step-body span { font-size: 13px; color: var(--muted); line-height: 1.5; }

/* Action btn */
.co-btn-primary { display: flex; align-items: center; justify-content: center; gap: 8px; width: 100%; background: var(--gold); color: #000; font-family: var(--font-head); font-size: 14px; font-weight: 700; letter-spacing: 0.5px; border: none; border-radius: 8px; padding: 15px 24px; cursor: pointer; transition: background 0.2s; text-decoration: none; }
.co-btn-primary:hover { background: #ffc820; color: #000; }
.co-btn-outline { display: flex; align-items: center; justify-content: center; gap: 8px; width: 100%; background: transparent; color: var(--gold); font-family: var(--font-head); font-size: 13px; font-weight: 700; letter-spacing: 0.5px; border: 1px solid rgba(245,184,0,0.35); border-radius: 8px; padding: 13px 24px; cursor: pointer; transition: background 0.2s, border-color 0.2s; text-decoration: none; margin-top: 10px; }
.co-btn-outline:hover { background: rgba(245,184,0,0.07); border-color: rgba(245,184,0,0.6); color: var(--gold); }

/* Alert success */
.co-alert-ok { background: rgba(34,197,94,0.07); border: 1px solid rgba(34,197,94,0.25); border-radius: 8px; padding: 13px 18px; margin-bottom: 20px; font-size: 13px; color: #6ee7a0; }
</style>

<div class="ty-page">

    {{-- Banner --}}
    <div class="co-banner">
        <div class="container">
            <div class="co-breadcrumb">Events / Checkout / <span style="color:var(--gold)">Confirmation</span></div>
            <h1>Order <span>Confirmed</span></h1>
        </div>
    </div>

    {{-- Body --}}
    <section>
        <div class="container">
            <div class="ty-layout">
                <div class="ty-box">

                    @if(session('success'))
                        <div class="co-alert-ok">{{ session('success') }}</div>
                    @endif

                    {{-- Main card --}}
                    <div class="co-card">

                        {{-- Icon --}}
                        <div class="ty-icon-wrap">
                            <div class="ty-icon-ring">🎟️</div>
                        </div>

                        <h2 class="ty-heading">Thank You for Your <span>Order!</span></h2>
                        <p class="ty-sub">Your order has been successfully submitted. We'll keep you updated on the next steps via email.</p>

                        <div class="ty-divider"></div>

                        {{-- Status --}}
                        <div class="co-label">01 &nbsp; Order Status</div>
                        <div class="ty-status-row">
                            <div class="ty-status-dot"></div>
                            <div class="ty-status-text">
                                <strong>Pending Admin Review</strong>
                                Your order is currently under review. Once approved, you'll receive a payment or confirmation link by email.
                            </div>
                        </div>

                        <div class="ty-divider"></div>

                        {{-- What's next --}}
                        <div class="co-label">02 &nbsp; What's Next</div>
                        <div class="ty-steps">
                            <div class="ty-step">
                                <div class="ty-step-num">1</div>
                                <div class="ty-step-body">
                                    <strong>Review & Approval</strong>
                                    <span>Our team will review your order details within 1–2 business days.</span>
                                </div>
                            </div>
                            <div class="ty-step">
                                <div class="ty-step-num">2</div>
                                <div class="ty-step-body">
                                    <strong>Email Notification</strong>
                                    <span>You'll receive an email with approval status and payment instructions if applicable.</span>
                                </div>
                            </div>
                            <div class="ty-step">
                                <div class="ty-step-num">3</div>
                                <div class="ty-step-body">
                                    <strong>Ticket Delivery</strong>
                                    <span>Once confirmed, your tickets will be sent to the email address provided.</span>
                                </div>
                            </div>
                        </div>

                        <div class="ty-divider"></div>

                        {{-- Actions --}}
                        <a href="{{ route('front.events') ?? '/' }}" class="co-btn-primary">🎉 Explore More Events</a>
                        <a href="{{ route('front.home') ?? '/' }}" class="co-btn-outline">← Back to Home</a>

                    </div>

                </div>
            </div>
        </div>
    </section>

</div>

@endsection
