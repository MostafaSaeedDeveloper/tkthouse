@extends('front.layout.app')

@section('title', 'Payment simulator')

@section('content')
<div class="tkt-container tkt-checkout">
    <div class="tkt-sim">
        <div class="tkt-alert tkt-alert--warning tkt-sim__banner" role="note">Test gateway — no real money is charged. Only available outside production.</div>
        <section class="tkt-card tkt-section-card">
            <h1 class="tkt-section-card__title">Simulated payment</h1>
            <p class="tkt-section-card__sub">Order {{ $attempt->order->order_number }} · {{ number_format((float) $attempt->amount, 2) }} EGP</p>
            <form method="POST" action="{{ route('front.payments.fake.complete', $attempt) }}" style="display:grid;gap:10px">
                @csrf
                <button class="tkt-btn tkt-btn--primary tkt-btn--lg tkt-btn--block" name="outcome" value="paid">Simulate successful payment</button>
                <button class="tkt-btn tkt-btn--outline tkt-btn--lg tkt-btn--block" name="outcome" value="failed">Simulate failed payment</button>
                <button class="tkt-btn tkt-btn--ghost tkt-btn--lg tkt-btn--block" name="outcome" value="pending">Leave pending</button>
            </form>
        </section>
    </div>
</div>
@endsection
