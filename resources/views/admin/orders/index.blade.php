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
        <div class="block-content py-3">
            <form method="GET" class="row g-2 align-items-end">
                <div class="col-md-3">
                    <label class="form-label">Search</label>
                    <input type="text" name="search" value="{{ request('search') }}" class="form-control" placeholder="Order # / customer">
                </div>
                <div class="col-md-2">
                    <label class="form-label">Status</label>
                    <select name="status" class="form-select js-select2">
                        <option value="">All</option>
                        @foreach(['pending_approval','pending_payment','on_hold','paid','canceled','rejected','refunded','partially_refunded'] as $status)
                            <option value="{{ $status }}" @selected(request('status') === $status)>{{ str($status)->headline() }}</option>
                        @endforeach
                    </select>
                </div>                <div class="col-md-2">
                    <label class="form-label">Payment Method</label>
                    <select name="payment_method" class="form-select js-select2">
                        <option value="">All</option>
                        @foreach(['cash','card','vodafone_cash','instapay','bank_transfer'] as $method)
                            <option value="{{ $method }}" @selected(request('payment_method') === $method)>{{ str($method)->headline() }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-md-3 d-flex gap-2">
                    <button class="btn btn-primary" type="submit">Filter</button>
                    <a class="btn btn-alt-secondary" href="{{ route('admin.orders.index') }}">Reset</a>
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
                            <th>Order #</th><th>Customer</th><th>Items</th><th>Ticket Types</th><th>Total</th><th>Status</th><th>Payment</th><th class="text-end">Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($orders as $order)
                        <tr>
                            <td class="fw-semibold">
                                <a href="{{ route('admin.orders.show', $order) }}">
                                    {{ preg_replace('/\D+/', '', (string) $order->order_number) ?: $order->order_number }}
                                </a>
                            </td>
                            <td>{{ $order->customer?->full_name }}<br><span class="fs-sm text-muted">{{ $order->customer?->email }}</span></td>
                            <td>{{ $order->items_count }}</td>
                            <td>
                                <div class="d-flex flex-wrap gap-1">
                                    @foreach($order->items->pluck('ticket_name')->unique() as $ticketType)
                                        @php
                                            $normalizedType = \Illuminate\Support\Str::lower(trim((string) \Illuminate\Support\Str::afterLast($ticketType, ' - ')));
                                            $ticketColor = $ticketColorMap[$normalizedType] ?? '#6c757d';
                                        @endphp
                                        <span class="badge" style="background-color: {{ $ticketColor }}; color: #fff;">{{ $ticketType }}</span>
                                    @endforeach
                                </div>
                            </td>
                            <td>{{ number_format($order->total_amount, 2) }} EGP</td>
                            <td><span class="badge bg-info">{{ ucwords(str_replace('_', ' ', $order->status)) }}</span></td>
                            <td>{{ ucwords(str_replace('_', ' ', $order->payment_method)) }}</td>
                            <td class="text-end">
                                @if($order->status === 'pending_approval')
                                    <form class="d-inline" method="POST" action="{{ route('admin.orders.approve', $order) }}">@csrf
                                        <button class="btn btn-sm btn-alt-success" type="submit" title="Approve"><i class="fa fa-check"></i></button>
                                    </form>
                                    <form class="d-inline" method="POST" action="{{ route('admin.orders.reject', $order) }}">@csrf
                                        <button class="btn btn-sm btn-alt-danger" type="submit" title="Reject"><i class="fa fa-times"></i></button>
                                    </form>
                                @endif
                                <a class="btn btn-sm btn-alt-primary" href="{{ route('admin.orders.show', $order) }}"><i class="fa fa-eye"></i></a>
                                <a class="btn btn-sm btn-alt-warning" href="{{ route('admin.orders.edit', $order) }}"><i class="fa fa-pen"></i></a>
                            </td>
                        </tr>
                        @empty
                        <tr><td colspan="8" class="text-center py-4 text-muted">No orders found.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <div class="mt-3">{{ $orders->links() }}</div>
</div>
@endsection
