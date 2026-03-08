@extends('admin.master')

@section('content')
<div class="content">
    @include('admin.partials.flash')

    <div class="d-md-flex justify-content-md-between align-items-md-center mb-3">
        <div>
            <h1 class="h3 mb-1">Deleted Orders</h1>
            <p class="text-muted mb-0">Soft-deleted orders can be restored from here.</p>
        </div>
        <div>
            <a class="btn btn-alt-primary" href="{{ route('admin.orders.index') }}">
                <i class="fa fa-arrow-left me-1"></i> Back to Orders
            </a>
        </div>
    </div>

    <div class="block block-rounded mb-3">
        <div class="block-content py-3">
            <form method="GET" class="row g-2 align-items-end">
                <div class="col-md-4">
                    <label class="form-label">Search</label>
                    <input type="text" name="search" value="{{ request('search') }}" class="form-control" placeholder="Order # / customer">
                </div>
                <div class="col-md-3">
                    <label class="form-label">Status</label>
                    <select name="status" class="form-select js-select2">
                        <option value="">All</option>
                        @foreach(['pending_approval','pending_payment','on_hold','paid','canceled','rejected','refunded','partially_refunded'] as $status)
                            <option value="{{ $status }}" @selected(request('status') === $status)>{{ str($status)->headline() }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-md-3">
                    <label class="form-label">Payment Method</label>
                    <select name="payment_method" class="form-select js-select2">
                        <option value="">All</option>
                        @foreach($paymentMethods as $method)
                            <option value="{{ $method->code }}" @selected(request('payment_method') === $method->code)>{{ $method->checkout_label ?: $method->name }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-md-2 d-flex gap-2">
                    <button class="btn btn-primary" type="submit">Filter</button>
                    <a class="btn btn-alt-secondary" href="{{ route('admin.orders.deleted') }}">Reset</a>
                </div>
            </form>
        </div>
    </div>

    <div class="block block-rounded">
        <div class="block-header block-header-default">
            <h3 class="block-title">Trash</h3>
            <div class="block-options">
                <span class="badge bg-warning text-dark">{{ $orders->total() }} Deleted</span>
            </div>
        </div>
        <div class="block-content p-0">
            <div class="table-responsive">
                <table class="table table-hover table-vcenter mb-0">
                    <thead>
                        <tr>
                            <th>Order #</th>
                            <th>Customer</th>
                            <th>Items</th>
                            <th>Total</th>
                            <th>Deleted At</th>
                            <th class="text-end">Action</th>
                        </tr>
                    </thead>
                    <tbody>
                    @forelse($orders as $order)
                        <tr>
                            <td class="fw-semibold">{{ preg_replace('/\D+/', '', (string) $order->order_number) ?: $order->order_number }}</td>
                            <td>{{ $order->customer?->full_name }}<br><span class="fs-sm text-muted">{{ $order->customer?->email }}</span></td>
                            <td>{{ $order->items_count }}</td>
                            <td>{{ number_format($order->total_amount, 2) }} EGP</td>
                            <td>{{ optional($order->deleted_at)->format('Y-m-d h:i A') }}</td>
                            <td class="text-end">
                                @can('orders.restore')
                                    <form method="POST" action="{{ route('admin.orders.restore', $order->id) }}" class="d-inline">
                                        @csrf
                                        <button class="btn btn-sm btn-alt-success" type="submit">
                                            <i class="fa fa-rotate-left me-1"></i> Restore
                                        </button>
                                    </form>
                                @endcan
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="6" class="text-center py-4 text-muted">No deleted orders found.</td>
                        </tr>
                    @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <div class="mt-3">{{ $orders->links() }}</div>
</div>
@endsection
