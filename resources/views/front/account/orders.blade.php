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
                <table class="acc-table">
                    <thead>
                        <tr>
                            <th>Order #</th>
                            <th>Status</th>
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
                            @endphp
                            <tr>
                                <td class="acc-mono">{{ $order->order_number }}</td>
                                <td><span class="acc-badge {{ $sc }}">{{ ucwords(str_replace('_',' ',$order->status)) }}</span></td>
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
                            <tr><td colspan="6"><div class="acc-empty"><i class="fa fa-bag-shopping"></i><span>No orders yet.</span></div></td></tr>
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

@endsection
