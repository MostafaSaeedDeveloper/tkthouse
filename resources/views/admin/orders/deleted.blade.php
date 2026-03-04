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
                                @can('orders.manage')
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
