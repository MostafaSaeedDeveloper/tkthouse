@extends('front.layout.master')

@section('content')
<div class="sub-banner">
    <div class="container">
        <h6>My Dashboard</h6>
    </div>
</div>

<section class="py-5" style="background:#090909;color:#fff;">
    <div class="container">
        @if(session('success'))
            <div class="alert alert-success">{{ session('success') }}</div>
        @endif

        <div class="d-flex justify-content-between align-items-center mb-4">
            <h4 class="text-warning mb-0">My Orders</h4>
            <div class="d-flex gap-2">
                <a href="{{ route('front.checkout') }}" class="btn btn-warning">New Checkout</a>
                <form method="POST" action="{{ route('front.customer.logout') }}">
                    @csrf
                    <button type="submit" class="btn btn-outline-light">Logout</button>
                </form>
            </div>
        </div>

        <div class="card bg-dark border-secondary">
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
                                    <td>{{ number_format($order->total_amount, 2) }}</td>
                                    <td>{{ $order->created_at?->format('Y-m-d H:i') }}</td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="5" class="text-center text-muted py-4">No orders yet.</td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <div class="mt-3">{{ $orders->links() }}</div>
    </div>
</section>
@endsection
