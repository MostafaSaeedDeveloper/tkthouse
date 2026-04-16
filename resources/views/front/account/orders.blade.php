@extends('front.layout.master')

@section('content')

@include('front.account.partials._acc_styles')

<div class="acc-banner">
    <div class="container">
        <div class="acc-banner-inner">
            <div>
                <div class="acc-banner-label">Account</div>
                <h1 class="acc-banner-title">My <span>Orders</span></h1>
                <p class="acc-banner-sub">Track and manage all your ticket orders</p>
            </div>
        </div>
    </div>
</div>

<section class="acc-page">
    <div class="container">

        @include('front.account.partials.navigation')

        <div class="acc-card">
            <div class="acc-card-head">
                <div class="acc-card-title">Order History</div>
            </div>
            <div style="overflow-x:auto;">
                <table class="acc-table acc-orders-table">
                    <thead>
                        <tr>
                            <th>Order #</th>
                            <th>Status</th>
                            <th>Payment Deadline</th>
                            <th>Payment Method</th>
                            <th>Total</th>
                            <th>Date</th>
                            <th class="text-end">Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($orders as $order)
                            @php
                                $sc = match($order->status) {
                                    'paid'                 => 'acc-badge-paid',
                                    'rejected','canceled'  => 'acc-badge-rejected',
                                    default                => 'acc-badge-pending',
                                };
                            @endphp
                            @php
                                $methodCode = (string) ($order->payment_method ?? '');
                                $methodLabel = $paymentMethodLabels[$methodCode] ?? ucwords(str_replace('_', ' ', $methodCode ?: 'N/A'));
                                $secondsLeft = $order->paymentSecondsRemaining();
                                $deadlineAt = $order->paymentDeadlineAt();
                                $safeSecondsLeft = $secondsLeft !== null ? max(0, (int) $secondsLeft) : null;
                                $initialTimerLabel = 'Expired';
                                if ($safeSecondsLeft !== null && $safeSecondsLeft > 0) {
                                    $days = intdiv($safeSecondsLeft, 86400);
                                    $hours = intdiv($safeSecondsLeft % 86400, 3600);
                                    $mins = intdiv($safeSecondsLeft % 3600, 60);
                                    $secs = $safeSecondsLeft % 60;
                                    $initialTimerLabel = ($days > 0 ? $days.'d ' : '')
                                        .str_pad((string) $hours, 2, '0', STR_PAD_LEFT).':'
                                        .str_pad((string) $mins, 2, '0', STR_PAD_LEFT).':'
                                        .str_pad((string) $secs, 2, '0', STR_PAD_LEFT);
                                }
                            @endphp
                            <tr>
                                <td class="acc-mono">{{ $order->order_number }}</td>
                                <td><span class="acc-badge {{ $sc }}">{{ ucwords(str_replace('_',' ',$order->status)) }}</span></td>
                                <td>
                                    @if($order->status === 'pending_payment' && $secondsLeft !== null)
                                        <span class="acc-deadline-chip js-order-timer" data-seconds-left="{{ $safeSecondsLeft }}">
                                            {{ $initialTimerLabel }}
                                        </span>
                                        <small class="acc-deadline-sub">Until: {{ $deadlineAt?->format('d M Y, h:i A') }}</small>
                                    @else
                                        <span style="color:var(--muted);font-size:11px;">—</span>
                                    @endif
                                </td>
                                <td><span class="acc-badge acc-badge-method">{{ $methodLabel }}</span></td>
                                <td style="font-weight:600;color:#fff;">{{ number_format($order->total_amount,2) }} <span style="color:var(--muted);font-size:11px;">EGP</span></td>
                                <td style="color:var(--muted);font-size:12px;">{{ $order->created_at?->format('d M Y, g:i A') }}</td>
                                <td class="text-end">
                                    @if($order->status === 'pending_payment' && $order->payment_link_token)
                                        <a href="{{ route('front.orders.payment', ['order' => $order, 'token' => $order->payment_link_token]) }}" class="acc-paynow-btn">Pay Now</a>
                                    @else
                                        <span style="color:var(--muted);font-size:11px;">—</span>
                                    @endif
                                </td>
                            </tr>
                        @empty
                            <tr><td colspan="7"><div class="acc-empty"><i class="fa fa-bag-shopping"></i><span>No orders yet.</span></div></td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
            @if($orders->hasPages())
                <div class="acc-page-footer">{{ $orders->links() }}</div>
            @endif
        </div>

    </div>
</section>

<script>
document.addEventListener('DOMContentLoaded', () => {
    const timers = document.querySelectorAll('.js-order-timer');
    if (!timers.length) return;

    const render = (el, seconds) => {
        if (seconds <= 0) {
            el.textContent = 'Expired';
            return;
        }

        const days = Math.floor(seconds / 86400);
        const hours = Math.floor((seconds % 86400) / 3600);
        const mins = Math.floor((seconds % 3600) / 60);
        const secs = seconds % 60;
        const dd = days > 0 ? `${days}d ` : '';
        el.textContent = `${dd}${String(hours).padStart(2, '0')}:${String(mins).padStart(2, '0')}:${String(secs).padStart(2, '0')}`;
    };

    timers.forEach((el) => {
        let remaining = parseInt(el.dataset.secondsLeft || '0', 10);
        render(el, remaining);
        setInterval(() => {
            remaining -= 1;
            render(el, remaining);
        }, 1000);
    });
});
</script>

@endsection
