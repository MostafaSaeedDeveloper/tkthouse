@extends('admin.master')

@section('content')
@php
    $displayOrderNumber = preg_replace('/\D+/', '', (string) $order->order_number) ?: $order->order_number;
@endphp

<div class="content">
    @include('admin.partials.flash')

    <div class="d-md-flex justify-content-md-between align-items-center mb-4">
        <div>
            <h1 class="h3 mb-1">Edit Order #{{ $displayOrderNumber }}</h1>
            <p class="text-muted mb-0">Update order details and ticket holders.</p>
        </div>
        <a href="{{ route('admin.orders.show', $order) }}" class="btn btn-alt-secondary mt-3 mt-md-0">Back to Order</a>
    </div>

    <form action="{{ route('admin.orders.update', $order) }}" method="POST">
        @csrf
        @method('PUT')

        <div class="row g-4">
            <div class="col-xl-8">
                <div class="block block-rounded">
                    <div class="block-header block-header-default">
                        <h3 class="block-title">Ticket Items</h3>
                    </div>
                    <div class="block-content">
                        @foreach($order->items as $item)
                            <div class="border rounded p-3 mb-3">
                                <input type="hidden" name="items[{{ $loop->index }}][id]" value="{{ $item->id }}">
                                <div class="d-flex justify-content-between mb-3">
                                    <h5 class="mb-0">{{ $item->ticket_name }}</h5>
                                    <span class="text-muted fs-sm">Unit Price: {{ number_format((float) $item->ticket_price, 2) }}</span>
                                </div>
                                <div class="row g-3">
                                    <div class="col-md-3">
                                        <label class="form-label">Quantity</label>
                                        <input type="number" min="1" class="form-control" name="items[{{ $loop->index }}][quantity]" value="{{ old("items.$loop->index.quantity", $item->quantity) }}">
                                    </div>
                                    <div class="col-md-9">
                                        <label class="form-label">Holder Name</label>
                                        <input type="text" class="form-control" name="items[{{ $loop->index }}][holder_name]" value="{{ old("items.$loop->index.holder_name", $item->holder_name) }}">
                                    </div>
                                    <div class="col-md-6">
                                        <label class="form-label">Holder Email</label>
                                        <input type="email" class="form-control" name="items[{{ $loop->index }}][holder_email]" value="{{ old("items.$loop->index.holder_email", $item->holder_email) }}">
                                    </div>
                                    <div class="col-md-6">
                                        <label class="form-label">Holder Phone</label>
                                        <input type="text" class="form-control" name="items[{{ $loop->index }}][holder_phone]" value="{{ old("items.$loop->index.holder_phone", $item->holder_phone) }}">
                                    </div>
                                </div>
                            </div>
                        @endforeach
                    </div>
                </div>
            </div>

            <div class="col-xl-4">
                <div class="block block-rounded">
                    <div class="block-header block-header-default">
                        <h3 class="block-title">Order Settings</h3>
                    </div>
                    <div class="block-content">
                        <div class="mb-3">
                            <label class="form-label">Status</label>
                            <input type="text" class="form-control" name="status" value="{{ old('status', $order->status) }}">
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Payment Method</label>
                            <input type="text" class="form-control" name="payment_method" value="{{ old('payment_method', $order->payment_method) }}">
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Payment Status</label>
                            <input type="text" class="form-control" name="payment_status" value="{{ old('payment_status', $order->payment_status) }}">
                        </div>
                        <div class="mb-3 form-check">
                            <input type="hidden" name="requires_approval" value="0">
                            <input class="form-check-input" type="checkbox" value="1" id="requires_approval" name="requires_approval" {{ old('requires_approval', $order->requires_approval) ? 'checked' : '' }}>
                            <label class="form-check-label" for="requires_approval">Requires Approval</label>
                        </div>
                    </div>
                    <div class="block-content block-content-full bg-body-light text-end">
                        <button type="submit" class="btn btn-primary"><i class="fa fa-save me-1"></i> Save Changes</button>
                    </div>
                </div>
            </div>
        </div>
    </form>
</div>
@endsection
