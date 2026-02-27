@extends('admin.master')

@section('content')
<div class="content">
    @include('admin.partials.flash')

    <div class="d-md-flex justify-content-md-between align-items-md-center mb-3">
        <div>
            <h1 class="h3 mb-1">Orders</h1>
            <p class="text-muted mb-0">Orders list.</p>
        </div>
    </div>

    <div class="block block-rounded block-bordered mb-3">
        <div class="block-content py-3">
            <form method="GET" action="{{ route('admin.orders.index') }}" class="row g-2 align-items-end">
                <div class="col-md-3">
                    <label class="form-label">Search</label>
                    <input type="text" class="form-control" name="search" value="{{ request('search') }}" placeholder="Order #, customer name, email">
                </div>
                <div class="col-md-3">
                    <label class="form-label">Order Status</label>
                    <select name="status" class="form-select js-select2" data-placeholder="All statuses" style="width: 100%;">
                        <option value="">All statuses</option>
                        @foreach(['pending_approval','pending_payment','on_hold','complete','canceled','rejected'] as $status)
                            <option value="{{ $status }}" @selected(request('status') === $status)>{{ str($status)->headline() }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-md-3">
                    <label class="form-label">Payment Status</label>
                    <select name="payment_status" class="form-select js-select2" data-placeholder="All payments" style="width: 100%;">
                        <option value="">All payments</option>
                        @foreach(['unpaid','pending','paid','refunded','partially_refunded'] as $paymentStatus)
                            <option value="{{ $paymentStatus }}" @selected(request('payment_status') === $paymentStatus)>{{ str($paymentStatus)->headline() }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-md-3 d-flex gap-2">
                    <button class="btn btn-primary w-100" type="submit"><i class="fa fa-filter me-1"></i> Filter</button>
                    <a href="{{ route('admin.orders.index') }}" class="btn btn-alt-secondary w-100">Reset</a>
                </div>
            </form>
        </div>
    </div>

    <div class="block block-rounded">
        <div class="block-header block-header-default">
            <h3 class="block-title">All Orders</h3>
            <div class="block-options">
                <span class="badge bg-primary">{{ $orders->total() }} Total</span>
            </div>
        </div>
        <div class="block-content p-0">
            <div class="table-responsive">
                <table class="table table-hover table-vcenter mb-0">
                    <thead>
                        <tr>
                            <th>Order #</th><th>Customer</th><th>Items</th><th>Total</th><th>Status</th><th>Payment</th><th class="text-end">Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($orders as $order)
                        <tr>
                            <td class="fw-semibold">
                                <a href="{{ route('admin.orders.show', $order) }}" class="text-primary">
                                    {{ preg_replace('/\D+/', '', (string) $order->order_number) ?: $order->order_number }}
                                </a>
                            </td>
                            <td>{{ $order->customer?->full_name }}<br><span class="fs-sm text-muted">{{ $order->customer?->email }}</span></td>
                            <td>{{ $order->items_count }}</td>
                            <td>{{ number_format($order->total_amount, 2) }} EGP</td>
                            <td><span class="badge bg-info">{{ ucwords(str_replace('_', ' ', $order->status)) }}</span></td>
                            <td>{{ ucwords(str_replace('_', ' ', $order->payment_method)) }}</td>
                            <td class="text-end">
                                @if($order->status === 'pending_approval')
                                    <form action="{{ route('admin.orders.approve', $order) }}" method="POST" class="d-inline">
                                        @csrf
                                        <button class="btn btn-sm btn-alt-success" type="submit" title="Approve"><i class="fa fa-check"></i></button>
                                    </form>
                                    <form action="{{ route('admin.orders.reject', $order) }}" method="POST" class="d-inline">
                                        @csrf
                                        <button class="btn btn-sm btn-alt-danger" type="submit" title="Reject"><i class="fa fa-times"></i></button>
                                    </form>
                                @endif
                                <a class="btn btn-sm btn-alt-primary" href="{{ route('admin.orders.show', $order) }}"><i class="fa fa-eye"></i></a>
                                <a class="btn btn-sm btn-alt-warning" href="{{ route('admin.orders.edit', $order) }}"><i class="fa fa-pen"></i></a>
                            </td>
                        </tr>
                        @empty
                        <tr><td colspan="7" class="text-center py-4 text-muted">No orders found.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <div class="mt-3">{{ $orders->links() }}</div>
</div>
@endsection
