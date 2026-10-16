@extends('admin.master')
@section('title', $customer->full_name)

@push('styles')<link rel="stylesheet" href="{{ asset('admin-v2/orders-customers.css') }}">@endpush

@section('content')
<x-admin.page-header :title="$customer->full_name" subtitle="Checkout customer record (buyer), not a portal login account." :crumbs="['Dashboard' => route('admin.dashboard'), 'Customers' => route('admin.customers.index'), $customer->full_name]">
    <a href="{{ route('admin.customers.index') }}" class="a-btn a-btn--ghost"><i class="fa fa-arrow-left"></i> Back</a>
</x-admin.page-header>

<x-admin.flash />

<div class="a-grid a-grid--4" style="margin-bottom:20px">
    <x-admin.kpi label="Customer since" :value="$customer->created_at->format('M d, Y')" icon="fa-calendar" />
    <x-admin.kpi label="Total orders" :value="number_format($customer->orders->count())" icon="fa-receipt" />
    <x-admin.kpi label="Total spent" :value="number_format($customer->orders->sum('total_amount'), 2).' EGP'" icon="fa-wallet" />
    <x-admin.kpi label="Contact" :value="$customer->phone ?: '—'" icon="fa-phone" :hint="$customer->email ?: null" />
</div>

<x-admin.card flush title="Orders" :subtitle="$customer->orders->count().' '.Str::plural('order', $customer->orders->count())">
    @if($customer->orders->isEmpty())
        <x-admin.empty icon="fa-receipt" title="No orders for this customer" />
    @else
    <x-admin.table stack caption="Customer orders">
        <thead><tr><th>Order</th><th class="a-num">Items</th><th class="a-num">Total</th><th>Date</th><th>Status</th><th class="a-num">Actions</th></tr></thead>
        <tbody>
        @foreach($customer->orders as $order)
            <tr>
                <td data-label="Order"><a class="a-table__main" href="{{ route('admin.orders.show', $order) }}">{{ preg_replace('/\D+/', '', (string) $order->order_number) ?: $order->order_number }}</a></td>
                <td data-label="Items" class="a-num">{{ $order->items_count }}</td>
                <td data-label="Total" class="a-num">{{ number_format($order->total_amount, 2) }} EGP</td>
                <td data-label="Date">{{ $order->created_at->format('M d, Y H:i') }}</td>
                <td data-label="Status"><x-admin.status :status="$order->status" kind="order" /></td>
                <td data-label="Actions"><div class="a-table__actions">
                    <a class="a-btn a-btn--outline a-btn--sm" href="{{ route('admin.orders.show', $order) }}" aria-label="View order"><i class="fa fa-eye"></i></a>
                </div></td>
            </tr>
        @endforeach
        </tbody>
    </x-admin.table>
    @endif
</x-admin.card>
@endsection
