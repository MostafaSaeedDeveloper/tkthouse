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

    <div class="block block-rounded mb-3">
        <div class="block-content">
            <form method="GET" action="{{ route('admin.orders.index') }}" class="row g-3 align-items-end">
                <div class="col-md-4">
                    <label class="form-label" for="orders-search">Search</label>
                    <input type="text" id="orders-search" name="search" class="form-control" value="{{ request('search') }}" placeholder="Order #, customer name, or email">
                </div>
                <div class="col-md-3">
                    <label class="form-label" for="orders-status">Status</label>
                    <select id="orders-status" name="status" class="form-select">
                        <option value="">All statuses</option>
                        @foreach(['pending_approval' => 'Pending Approval', 'pending_payment' => 'Pending Payment', 'on_hold' => 'On Hold', 'complete' => 'Complete', 'canceled' => 'Canceled', 'rejected' => 'Rejected'] as $value => $label)
                            <option value="{{ $value }}" @selected(request('status') === $value)>{{ $label }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-md-3">
                    <label class="form-label" for="orders-payment-method">Payment Method</label>
                    <select id="orders-payment-method" name="payment_method" class="form-select">
                        <option value="">All payment methods</option>
                        @foreach(['pending_review' => 'Pending Review', 'manual' => 'Manual', 'visa' => 'Visa', 'mada' => 'Mada', 'mastercard' => 'Mastercard', 'cash' => 'Cash', 'bank_transfer' => 'Bank Transfer'] as $value => $label)
                            <option value="{{ $value }}" @selected(request('payment_method') === $value)>{{ $label }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-md-2 d-flex gap-2">
                    <button type="submit" class="btn btn-primary w-100">Filter</button>
                    <a href="{{ route('admin.orders.index') }}" class="btn btn-alt-secondary">Reset</a>
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
                                <a href="{{ route('admin.orders.show', $order) }}" class="link-fx text-body-color">
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
                                        <button type="submit" class="btn btn-sm btn-alt-success" title="Approve Order">
                                            <i class="fa fa-circle-check"></i>
                                        </button>
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
