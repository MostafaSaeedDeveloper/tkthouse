@extends('admin.master')
@section('title', 'Deleted Orders')

@push('styles')<link rel="stylesheet" href="{{ asset('admin-v2/orders-customers.css') }}">@endpush

@section('content')
@php $statusList = ['pending_approval','pending_payment','on_hold','paid','canceled','rejected','refunded','partially_refunded']; @endphp
<x-admin.page-header title="Deleted Orders" subtitle="Soft-deleted orders can be restored from here." :crumbs="['Dashboard' => route('admin.dashboard'), 'Orders' => route('admin.orders.index'), 'Deleted']">
    <a class="a-btn a-btn--outline" href="{{ route('admin.orders.index') }}"><i class="fa fa-arrow-left"></i> Back to Orders</a>
</x-admin.page-header>

<x-admin.flash />

<x-admin.filters :action="route('admin.orders.deleted')" :reset="route('admin.orders.deleted')">
    <div class="a-field"><label class="form-label" for="dSearch">Search</label>
        <input type="text" id="dSearch" name="search" value="{{ request('search') }}" class="form-control" placeholder="Order # / customer"></div>
    <div class="a-field"><label class="form-label" for="dStatus">Status</label>
        <select name="status" id="dStatus" class="form-select no-select2">
            <option value="">All</option>
            @foreach($statusList as $status)
                <option value="{{ $status }}" @selected(request('status') === $status)>{{ str($status)->headline() }}</option>
            @endforeach
        </select></div>
    <div class="a-field"><label class="form-label" for="dPay">Payment method</label>
        <select name="payment_method" id="dPay" class="form-select no-select2">
            <option value="">All</option>
            @foreach($paymentMethods as $method)
                <option value="{{ $method->code }}" @selected(request('payment_method') === $method->code)>{{ $method->checkout_label ?: $method->name }}</option>
            @endforeach
        </select></div>
    @if($canFilterByEvent)
    <div class="a-field"><label class="form-label" for="dEvent">Event</label>
        <select name="event_id" id="dEvent" class="form-select no-select2">
            <option value="">All Events</option>
            @foreach($events as $event)
                <option value="{{ $event->id }}" @selected(request('event_id') == $event->id)>{{ $event->name }}</option>
            @endforeach
        </select></div>
    @endif
</x-admin.filters>

<x-admin.card flush title="Trash" :subtitle="$orders->total().' deleted'">
    @if($orders->isEmpty())
        <x-admin.empty icon="fa-trash-can" title="No deleted orders found" text="Deleted orders will appear here and can be restored." />
    @else
    <x-admin.table stack caption="Deleted orders">
        <thead><tr><th>Order</th><th>Customer</th><th class="a-num">Items</th><th class="a-num">Total</th><th>Deleted at</th><th class="a-num">Actions</th></tr></thead>
        <tbody>
        @foreach($orders as $order)
            <tr>
                <td data-label="Order"><span class="a-table__main">{{ preg_replace('/\D+/', '', (string) $order->order_number) ?: $order->order_number }}</span></td>
                <td data-label="Customer" class="a-wrap"><span class="a-table__main">{{ $order->customer?->full_name }}</span><span class="a-table__sub">{{ $order->customer?->email }}</span></td>
                <td data-label="Items" class="a-num">{{ $order->items_count }}</td>
                <td data-label="Total" class="a-num">{{ number_format($order->total_amount, 2) }} EGP</td>
                <td data-label="Deleted at">{{ optional($order->deleted_at)->format('Y-m-d h:i A') }}</td>
                <td data-label="Actions"><div class="a-table__actions">
                    @can('orders.restore')
                        <form method="POST" action="{{ route('admin.orders.restore', $order->id) }}" data-a-confirm="Restore this order?" data-a-confirm-title="Restore order" data-a-confirm-ok="Restore">
                            @csrf
                            <button class="a-btn a-btn--success a-btn--sm" type="submit"><i class="fa fa-rotate-left"></i> Restore</button>
                        </form>
                    @endcan
                </div></td>
            </tr>
        @endforeach
        </tbody>
    </x-admin.table>
    <x-admin.pager :paginator="$orders" />
    @endif
</x-admin.card>
@endsection
