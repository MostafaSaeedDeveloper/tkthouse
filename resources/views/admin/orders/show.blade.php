@extends('admin.master')

@section('content')
@php
    $displayOrderNumber = preg_replace('/\D+/', '', (string) $order->order_number) ?: $order->order_number;
    $statusLabel = ucwords(str_replace('_', ' ', (string) $order->status));
    $paymentMethodLabel = ucwords(str_replace('_', ' ', (string) $order->payment_method));
    $paymentStatusLabel = ucwords(str_replace('_', ' ', (string) $order->payment_status));
    $customerInitials = collect(explode(' ', (string) ($order->customer?->full_name ?? '')))
        ->filter()
        ->map(fn ($part) => mb_substr($part, 0, 1))
        ->take(2)
        ->implode('');
@endphp

<div class="content">
    @include('admin.partials.flash')

    <div class="d-md-flex justify-content-md-between align-items-center mb-4">
        <div>
            <h1 class="h3 mb-1">Order #{{ $displayOrderNumber }}</h1>
            <p class="text-muted mb-0">Created {{ $order->created_at?->format('d M Y, h:i A') }}</p>
        </div>
        <div class="d-flex gap-2 mt-3 mt-md-0">
            <a href="{{ route('admin.orders.edit', $order) }}" class="btn btn-alt-primary">
                <i class="fa fa-pen me-1"></i> Edit Order
            </a>
            @if($order->status === 'pending_approval')
                <form method="POST" action="{{ route('admin.orders.approve', $order) }}">
                    @csrf
                    <button type="submit" class="btn btn-primary"><i class="fa fa-check me-1"></i> Approve</button>
                </form>
            @endif
            <a href="{{ route('admin.orders.index') }}" class="btn btn-alt-secondary">Back</a>
        </div>
    </div>

    <div class="row g-4">
        <div class="col-xl-8">
            <div class="block block-rounded">
                <div class="block-header block-header-default">
                    <h3 class="block-title">Ticket Items</h3>
                </div>
                <div class="block-content">
                    @forelse($order->items as $item)
                        <div class="border rounded p-3 mb-3">
                            <div class="d-flex justify-content-between align-items-start mb-2">
                                <div>
                                    <h5 class="mb-1">{{ $item->ticket_name }}</h5>
                                    <div class="fs-sm text-muted">Unit Price: {{ number_format((float) $item->ticket_price, 2) }}</div>
                                </div>
                                <div class="text-end">
                                    <div class="fw-bold text-primary">{{ number_format((float) $item->line_total, 2) }}</div>
                                    <div class="fs-sm text-muted">Qty × {{ $item->quantity }}</div>
                                </div>
                            </div>
                            <div class="fs-sm">
                                <div><strong>Holder:</strong> {{ $item->holder_name }}</div>
                                <div><strong>Email:</strong> {{ $item->holder_email }}</div>
                                <div><strong>Phone:</strong> {{ $item->holder_phone ?: '-' }}</div>
                            </div>
                        </div>
                    @empty
                        <p class="text-muted">No ticket items found for this order.</p>
                    @endforelse
                </div>
            </div>
        </div>

        <div class="col-xl-4">
            <div class="block block-rounded">
                <div class="block-header block-header-default">
                    <h3 class="block-title">Order Details</h3>
                </div>
                <div class="block-content">
                    <div class="mb-2"><strong>Status:</strong> {{ $statusLabel }}</div>
                    <div class="mb-2"><strong>Payment Method:</strong> {{ $paymentMethodLabel }}</div>
                    <div class="mb-2"><strong>Payment Status:</strong> {{ $paymentStatusLabel }}</div>
                    <div class="mb-2"><strong>Approval Required:</strong> {{ $order->requires_approval ? 'Yes' : 'No' }}</div>
                    <div class="mb-2"><strong>Approved At:</strong> {{ $order->approved_at?->format('d M Y, h:i A') ?? '-' }}</div>
                    <div class="mb-2"><strong>Total Amount:</strong> {{ number_format((float) $order->total_amount, 2) }}</div>
                </div>
            </div>

            <div class="block block-rounded">
                <div class="block-header block-header-default">
                    <h3 class="block-title">Customer</h3>
                </div>
                <div class="block-content">
                    <div class="d-flex align-items-center mb-3">
                        <div class="rounded-circle bg-primary text-white d-inline-flex align-items-center justify-content-center me-2" style="width:40px;height:40px;">
                            {{ $customerInitials ?: 'NA' }}
                        </div>
                        <div>
                            <div class="fw-semibold">{{ $order->customer?->full_name ?: 'N/A' }}</div>
                            <div class="fs-sm text-muted">{{ $order->customer?->email ?: '-' }}</div>
                        </div>
                    </div>
                    <div class="fs-sm"><strong>Phone:</strong> {{ $order->customer?->phone ?: '-' }}</div>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection
