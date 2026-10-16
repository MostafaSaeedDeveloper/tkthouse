@extends('admin.master')
@section('title', $customer->full_name)


@section('content')
<x-admin.page-header :title="$customer->full_name" subtitle="Checkout customer record (buyer), not a portal login account." :crumbs="['Dashboard' => route('admin.dashboard'), 'Customers' => route('admin.customers.index'), $customer->full_name]">
    <a href="{{ route('admin.customers.index') }}" class="a-btn a-btn--ghost"><i class="fa fa-arrow-left"></i> Back</a>
</x-admin.page-header>

<x-admin.flash />

<div class="a-grid a-grid--4 a-cus-kpis">
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
        <thead><tr><th>Order</th><th class="a-num">Total</th><th>Status</th><th class="a-num"><span class="a-sr-only">Actions</span></th></tr></thead>
        <tbody>
        @foreach($customer->orders as $order)
            <tr>
                <td data-label="Order"><div class="a-cell"><a class="a-table__main a-num-tab" href="{{ route('admin.orders.show', $order) }}">{{ preg_replace('/\D+/', '', (string) $order->order_number) ?: $order->order_number }}</a><span class="a-table__sub">{{ $order->created_at->format('d M Y, H:i') }} · {{ $order->items_count }} {{ Str::plural('ticket', $order->items_count) }}</span></div></td>
                <td data-label="Total" class="a-num"><div class="a-cell"><b>{{ number_format($order->total_amount, 2) }}</b> <span class="a-muted">EGP</span></div></td>
                <td data-label="Status"><div class="a-cell"><x-admin.status :status="$order->status" kind="order" /></div></td>
                <td data-label=""><div class="a-table__actions">
                    <a class="a-btn a-btn--outline a-btn--sm" href="{{ route('admin.orders.show', $order) }}">View</a>
                </div></td>
            </tr>
        @endforeach
        </tbody>
    </x-admin.table>
    @endif
</x-admin.card>
@endsection
