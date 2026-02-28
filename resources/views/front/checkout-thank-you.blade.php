@extends('front.layout.master')

@section('content')
@php
    $flow = $flow ?? 'pending_review';
    $isPaid = $flow === 'payment_success';
    $isFailed = $flow === 'payment_failed';

    $state = $isPaid
        ? [
            'badge' => 'ONLINE PAYMENT',
            'icon' => '✅',
            'title' => 'Payment Completed Successfully',
            'subtitle' => 'Your booking is confirmed and your tickets are now reserved for you.',
            'accent' => '#22c55e',
            'panel' => 'rgba(34,197,94,.10)',
            'border' => 'rgba(34,197,94,.35)',
            'steps' => [
                'A receipt has been sent to your buyer email.',
                'Each attendee will receive their ticket on email.',
                'You can always view tickets from your account dashboard.',
            ],
        ]
        : ($isFailed
            ? [
                'badge' => 'PAYMENT FAILED',
                'icon' => '❌',
                'title' => 'We Couldn\'t Complete Your Payment',
                'subtitle' => 'Don\'t worry — your order details are still saved and you can try again.',
                'accent' => '#ef4444',
                'panel' => 'rgba(239,68,68,.10)',
                'border' => 'rgba(239,68,68,.35)',
                'steps' => [
                    'Open your orders and select this order.',
                    'Choose an available payment method and retry.',
                    'Contact support if the issue continues.',
                ],
            ]
            : [
                'badge' => 'PENDING APPROVAL',
                'icon' => '⏳',
                'title' => 'Order Submitted for Approval',
                'subtitle' => 'Your request was sent successfully. Payment will be requested after admin approval.',
                'accent' => '#f5b800',
                'panel' => 'rgba(245,184,0,.10)',
                'border' => 'rgba(245,184,0,.35)',
                'steps' => [
                    'Our team reviews your request shortly.',
                    'After approval, you\'ll receive a payment link by email.',
                    'Once payment is done, tickets will be issued automatically.',
                ],
            ]);
@endphp

<style>
    .ty-page {
        background: radial-gradient(circle at top right, rgba(245, 184, 0, .10), transparent 45%), #060608;
        min-height: 100vh;
        padding: 64px 0;
        color: #f3f3f7;
    }
    .ty-card {
        max-width: 860px;
        margin: 0 auto;
        background: linear-gradient(170deg, #12131c, #0b0c12);
        border: 1px solid rgba(255,255,255,.08);
        border-radius: 20px;
        padding: 28px;
        box-shadow: 0 22px 45px rgba(0,0,0,.45);
    }
    .ty-hero {
        border-radius: 16px;
        padding: 22px;
        border: 1px solid {{ $state['border'] }};
        background: {{ $state['panel'] }};
        margin-bottom: 22px;
    }
    .ty-badge {
        display: inline-flex;
        align-items: center;
        gap: 8px;
        font-size: 11px;
        letter-spacing: 2px;
        text-transform: uppercase;
        color: {{ $state['accent'] }};
        border: 1px solid {{ $state['border'] }};
        border-radius: 999px;
        padding: 6px 12px;
        margin-bottom: 12px;
        font-weight: 700;
    }
    .ty-title {
        font-weight: 800;
        letter-spacing: -.2px;
        margin: 0 0 8px;
        font-size: clamp(24px, 5vw, 34px);
        line-height: 1.15;
    }
    .ty-subtitle {
        color: #c5c9de;
        margin: 0;
        font-size: 15px;
    }
    .ty-grid {
        display: grid;
        grid-template-columns: 1.1fr .9fr;
        gap: 16px;
        margin-bottom: 18px;
    }
    .ty-panel {
        background: #10121a;
        border: 1px solid rgba(255,255,255,.08);
        border-radius: 14px;
        padding: 18px;
    }
    .ty-panel h4 {
        margin: 0 0 12px;
        font-size: 12px;
        color: #f5b800;
        letter-spacing: 2px;
        text-transform: uppercase;
    }
    .ty-steps {
        list-style: none;
        margin: 0;
        padding: 0;
    }
    .ty-steps li {
        display: flex;
        gap: 10px;
        align-items: flex-start;
        color: #d8ddf2;
        font-size: 14px;
        line-height: 1.5;
        margin-bottom: 10px;
    }
    .ty-steps li:last-child { margin-bottom: 0; }
    .ty-step-num {
        display: inline-flex;
        width: 22px;
        height: 22px;
        flex-shrink: 0;
        border-radius: 999px;
        align-items: center;
        justify-content: center;
        background: rgba(245,184,0,.16);
        border: 1px solid rgba(245,184,0,.35);
        color: #f5b800;
        font-size: 12px;
        font-weight: 700;
    }
    .ty-order {
        display: flex;
        justify-content: space-between;
        align-items: center;
        gap: 12px;
        margin-bottom: 16px;
        padding: 12px 14px;
        border: 1px dashed rgba(255,255,255,.18);
        border-radius: 10px;
        background: rgba(255,255,255,.02);
    }
    .ty-order small { color: #98a0bf; text-transform: uppercase; letter-spacing: 1px; }
    .ty-order strong { color: #fff; font-size: 15px; }
    .ty-actions {
        display: flex;
        flex-wrap: wrap;
        gap: 10px;
    }
    .ty-btn {
        display: inline-flex;
        align-items: center;
        justify-content: center;
        border-radius: 10px;
        padding: 11px 16px;
        font-weight: 700;
        text-decoration: none;
        font-size: 14px;
    }
    .ty-btn-primary { background: #f5b800; color: #111; }
    .ty-btn-secondary { background: #1a1e2d; color: #f1f4ff; border: 1px solid rgba(255,255,255,.14); }
    .ty-btn-ghost { background: transparent; color: #d8deff; border: 1px solid rgba(255,255,255,.22); }

    @media (max-width: 900px) {
        .ty-page { padding: 36px 0 56px; }
        .ty-card { padding: 18px; border-radius: 16px; }
        .ty-grid { grid-template-columns: 1fr; }
        .ty-order { flex-direction: column; align-items: flex-start; }
    }
</style>

<section class="ty-page">
    <div class="container">
        <div class="ty-card">
            <div class="ty-hero">
                <div class="ty-badge">{{ $state['icon'] }} {{ $state['badge'] }}</div>
                <h1 class="ty-title">{{ $state['title'] }}</h1>
                <p class="ty-subtitle">{{ $state['subtitle'] }}</p>
            </div>

            @if(session('success'))
                <div style="margin-bottom:16px;padding:10px 12px;border-radius:10px;background:rgba(34,197,94,.12);border:1px solid rgba(34,197,94,.35);">
                    {{ session('success') }}
                </div>
            @endif

            <div class="ty-grid">
                <div class="ty-panel">
                    <h4>What happens next</h4>
                    <ul class="ty-steps">
                        @foreach($state['steps'] as $index => $step)
                            <li><span class="ty-step-num">{{ $index + 1 }}</span><span>{{ $step }}</span></li>
                        @endforeach
                    </ul>
                </div>
                <div class="ty-panel">
                    <h4>Order details</h4>
                    <div class="ty-order">
                        <div>
                            <small>Order Number</small><br>
                            <strong>{{ $orderNumber ?: 'Not available' }}</strong>
                        </div>
                        <div>
                            <small>Flow</small><br>
                            <strong>{{ str_replace('_', ' ', $flow) }}</strong>
                        </div>
                    </div>
                    <p style="color:#aeb6d4;font-size:13px;line-height:1.6;margin:0;">
                        If you need help regarding this booking, contact support and share your order number for faster assistance.
                    </p>
                </div>
            </div>

            <div class="ty-actions">
                <a href="{{ route('front.account.orders') }}" class="ty-btn ty-btn-primary">My Orders</a>
                <a href="{{ route('front.account.tickets') }}" class="ty-btn ty-btn-secondary">My Tickets</a>
                <a href="{{ route('front.events') }}" class="ty-btn ty-btn-ghost">Browse Events</a>
            </div>
        </div>
    </div>
</section>
@endsection
