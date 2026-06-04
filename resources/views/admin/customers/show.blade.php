@extends('admin.master')

@section('content')
<div class="content">
    @include('admin.partials.flash')

    {{-- Breadcrumb --}}
    <nav aria-label="breadcrumb" class="mb-3">
        <ol class="breadcrumb">
            <li class="breadcrumb-item"><a href="{{ route('admin.customers.index') }}" class="text-warning">Customers</a></li>
            <li class="breadcrumb-item active">{{ $customer->full_name }}</li>
        </ol>
    </nav>

    {{-- Profile Card --}}
    <div class="block block-rounded mb-4">
        <div class="block-content py-4 px-4">
            <div class="d-flex flex-md-row flex-column align-items-md-center gap-4">

                {{-- Avatar --}}
                <div class="flex-shrink-0">
                    <div class="rounded-circle d-flex align-items-center justify-content-center fw-bold fs-2 text-white"
                         style="width:90px;height:90px;background:linear-gradient(135deg,#c8972b,#e6b84a);letter-spacing:1px;">
                        {{ strtoupper(substr($customer->first_name, 0, 1) . substr($customer->last_name, 0, 1)) }}
                    </div>
                </div>

                {{-- Info --}}
                <div class="flex-grow-1">
                    <h2 class="h4 fw-bold mb-1">{{ $customer->full_name }}</h2>
                    <div class="d-flex flex-wrap gap-3 text-muted fs-sm mt-2">
                        @if($customer->email)
                        <span><i class="fa fa-envelope me-1"></i>{{ $customer->email }}</span>
                        @endif
                        @if($customer->phone)
                        <span><i class="fa fa-phone me-1"></i>{{ $customer->phone }}</span>
                        @endif
                    </div>
                </div>

                {{-- Stats --}}
                <div class="d-flex gap-4 flex-shrink-0 flex-wrap">
                    <div class="text-center">
                        <div class="text-muted fs-xs text-uppercase fw-semibold mb-1">Customer Since</div>
                        <div class="fw-semibold">{{ $customer->created_at->format('M d, Y') }}</div>
                    </div>
                    <div class="text-center">
                        <div class="text-muted fs-xs text-uppercase fw-semibold mb-1">Total Orders</div>
                        <div class="fw-bold fs-4">{{ $customer->orders->count() }}</div>
                    </div>
                    <div class="text-center">
                        <div class="text-muted fs-xs text-uppercase fw-semibold mb-1">Total Spent</div>
                        <div class="fw-bold fs-5 text-warning">{{ number_format($customer->orders->sum('total_amount'), 2) }} EGP</div>
                    </div>
                </div>

            </div>
        </div>
    </div>

    {{-- Orders Table --}}
    <div class="block block-rounded">
        <div class="block-header block-header-default">
            <h3 class="block-title">Orders</h3>
            <div class="block-options">
                <span class="badge bg-primary">{{ $customer->orders->count() }} {{ Str::plural('order', $customer->orders->count()) }}</span>
            </div>
        </div>
        <div class="block-content p-0">
            <div class="table-responsive">
                <table class="table table-hover table-vcenter mb-0">
                    <thead>
                        <tr>
                            <th>Order ID</th>
                            <th>Items</th>
                            <th>Total</th>
                            <th>Date</th>
                            <th>Status</th>
                            <th class="text-end">Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($customer->orders as $order)
                        @php
                            $statusClass = match($order->status) {
                                'paid' => 'bg-success',
                                'pending_approval', 'pending_payment', 'on_hold' => 'bg-warning text-dark',
                                'refunded', 'partially_refunded' => 'bg-primary',
                                default => 'bg-danger',
                            };
                            $statusLabel = match($order->status) {
                                'paid' => 'Completed',
                                default => ucwords(str_replace('_', ' ', $order->status)),
                            };
                        @endphp
                        <tr>
                            <td class="fw-semibold">
                                <a href="{{ route('admin.orders.show', $order) }}" class="text-warning text-decoration-none">
                                    {{ preg_replace('/\D+/', '', (string) $order->order_number) ?: $order->order_number }}
                                </a>
                            </td>
                            <td>{{ $order->items_count }}</td>
                            <td class="fw-semibold text-warning">{{ number_format($order->total_amount, 2) }} EGP</td>
                            <td>{{ $order->created_at->format('M d, Y H:i') }}</td>
                            <td><span class="badge {{ $statusClass }}">{{ $statusLabel }}</span></td>
                            <td class="text-end">
                                <a class="btn btn-sm btn-alt-primary" href="{{ route('admin.orders.show', $order) }}">
                                    <i class="fa fa-eye"></i>
                                </a>
                            </td>
                        </tr>
                        @empty
                        <tr><td colspan="6" class="text-center py-4 text-muted">No orders for this customer.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>

</div>
@endsection
