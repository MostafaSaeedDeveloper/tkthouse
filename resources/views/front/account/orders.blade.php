@extends('front.layout.master')

@section('content')
<div class="sub-banner"><div class="container"><h6>My Dashboard</h6></div></div>
<section class="py-5" style="background:#090909;color:#fff;">
    <div class="container">
        @include('front.account.partials.navigation')

        <div class="card bg-dark border-secondary">
            <div class="card-header border-secondary text-warning">My Orders</div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-dark table-hover mb-0 align-middle">
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
                            <tr>
                                <td>{{ $order->order_number }}</td>
                                <td>{{ ucwords(str_replace('_', ' ', $order->status)) }}</td>
                                <td>{{ ucwords(str_replace('_', ' ', $order->payment_status)) }}</td>
                                <td>{{ number_format($order->total_amount, 2) }} EGP</td>
                                <td>{{ $order->created_at?->format('Y-m-d H:i') }}</td>
                            </tr>
                        @empty
                            <tr><td colspan="5" class="text-center text-muted py-4">No orders yet.</td></tr>
                        @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
            <div class="card-footer border-secondary">{{ $orders->links() }}</div>
        </div>
    </div>
</section>
@endsection
