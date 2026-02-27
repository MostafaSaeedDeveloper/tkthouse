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
                            <th>Payment</th>
                            <th>Total</th>
                            <th>Date</th>
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
                                $pc = match($order->payment_status ?? '') {
                                    'paid'   => 'acc-badge-paid',
                                    'failed' => 'acc-badge-rejected',
                                    default  => 'acc-badge-default',
                                };
                            @endphp
                            <tr>
                                <td class="acc-mono">{{ $order->order_number }}</td>
                                <td><span class="acc-badge {{ $sc }}">{{ ucwords(str_replace('_',' ',$order->status)) }}</span></td>
                                <td><span class="acc-badge {{ $pc }}">{{ ucwords(str_replace('_',' ',$order->payment_status ?? 'pending')) }}</span></td>
                                <td style="font-weight:600;color:#fff;">{{ number_format($order->total_amount,2) }} <span style="color:var(--muted);font-size:11px;">EGP</span></td>
                                <td style="color:var(--muted);font-size:12px;">{{ $order->created_at?->format('d M Y, g:i A') }}</td>
                            </tr>
                        @empty
                            <tr><td colspan="5"><div class="acc-empty"><i class="fa fa-bag-shopping"></i><span>No orders yet.</span></div></td></tr>
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
