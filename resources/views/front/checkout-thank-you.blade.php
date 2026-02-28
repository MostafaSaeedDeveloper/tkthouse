@extends('front.layout.master')

@section('content')
@php
    $flow = $flow ?? 'pending_review';
    $isPaid = $flow === 'payment_success';
    $isFailed = $flow === 'payment_failed';
@endphp

<section style="background:#060608;min-height:100vh;padding:80px 0;color:#f3f3f7;">
    <div class="container" style="max-width:760px;">
        <div style="background:#11111a;border:1px solid rgba(255,255,255,.09);border-radius:16px;padding:28px;">
            <h2 style="font-weight:800;margin-bottom:10px;color:#f5b800;">
                @if($isPaid)
                    ✅ Payment completed successfully
                @elseif($isFailed)
                    ❌ Payment failed
                @else
                    ⏳ Order received
                @endif
            </h2>

            @if($orderNumber)
                <p style="color:#b8bfd8;margin-bottom:14px;">Order #: <strong style="color:#fff;">{{ $orderNumber }}</strong></p>
            @endif

            @if(session('success'))
                <p style="background:rgba(34,197,94,.12);border:1px solid rgba(34,197,94,.35);padding:10px 12px;border-radius:10px;">{{ session('success') }}</p>
            @endif

            @if($isPaid)
                <p>Your payment is confirmed. Tickets will be sent to each holder email address.</p>
                <p>A payment invoice has been sent to the buyer email, and tickets are available in your account.</p>
            @elseif($isFailed)
                <p>The payment was not completed. Please return to your order and try again with another payment method.</p>
            @else
                <p>Your order is under review. Once approved, a payment link will be sent to your email.</p>
            @endif

            <div style="display:flex;gap:10px;flex-wrap:wrap;margin-top:20px;">
                <a href="{{ route('front.account.orders') }}" class="btn" style="background:#f5b800;color:#111;font-weight:700;">My Orders</a>
                <a href="{{ route('front.account.tickets') }}" class="btn" style="background:#1e2335;color:#fff;border:1px solid rgba(255,255,255,.15);">My Tickets</a>
                <a href="{{ route('front.events') }}" class="btn" style="background:transparent;color:#d8deff;border:1px solid rgba(255,255,255,.25);">Events</a>
            </div>
        </div>
    </div>
</section>
@endsection
