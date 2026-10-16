@extends('admin.master')
@section('title', 'Deleted Orders')


@section('content')
@php $statusList = ['pending_approval','pending_payment','on_hold','paid','canceled','rejected','refunded','partially_refunded']; @endphp
<x-admin.page-header title="Deleted Orders" subtitle="Soft-deleted orders can be restored from here." :crumbs="['Dashboard' => route('admin.dashboard'), 'Orders' => route('admin.orders.index'), 'Deleted']">
    <a class="a-btn a-btn--outline" href="{{ route('admin.orders.index') }}"><i class="fa fa-arrow-left"></i> Back to Orders</a>
</x-admin.page-header>

<x-admin.flash />

<x-admin.filters :action="route('admin.orders.deleted')" :reset="route('admin.orders.deleted')">
    <x-admin.search name="search" :value="request('search')" placeholder="Order # / customer" />
    <x-admin.select label="Status" name="status" id="dStatus" class="no-select2" placeholder="All" :value="request('status')" :options="collect($statusList)->mapWithKeys(fn ($st) => [$st => str($st)->headline()])->all()" />
    <x-admin.select label="Payment method" name="payment_method" id="dPay" class="no-select2" placeholder="All" :value="request('payment_method')" :options="$paymentMethods->mapWithKeys(fn ($m) => [$m->code => $m->checkout_label ?: $m->name])->all()" />
    @if($canFilterByEvent)
        <x-admin.select label="Event" name="event_id" id="dEvent" class="no-select2" placeholder="All Events" :value="request('event_id')" :options="$events->mapWithKeys(fn ($e) => [$e->id => $e->name])->all()" />
    @endif
</x-admin.filters>

<x-admin.card flush title="Trash" :subtitle="$orders->total().' deleted'">
    @if($orders->isEmpty())
        <x-admin.empty icon="fa-trash-can" title="No deleted orders found" text="Deleted orders will appear here and can be restored." />
    @else
    <x-admin.table stack caption="Deleted orders">
        <thead><tr><th>Order</th><th>Customer</th><th class="a-num">Total</th><th>Deleted at</th><th class="a-num"><span class="a-sr-only">Actions</span></th></tr></thead>
        <tbody>
        @foreach($orders as $order)
            <tr>
                <td data-label="Order"><div class="a-cell"><span class="a-table__main a-num-tab">{{ preg_replace('/\D+/', '', (string) $order->order_number) ?: $order->order_number }}</span>
                    <span class="a-table__sub">{{ $order->items_count }} {{ \Illuminate\Support\Str::plural('ticket', $order->items_count) }}</span></div></td>
                <td data-label="Customer"><div class="a-cell"><span class="a-table__main a-trunc" title="{{ $order->customer?->full_name }}">{{ $order->customer?->full_name ?: '—' }}</span><span class="a-table__sub a-trunc" title="{{ $order->customer?->email }}">{{ $order->customer?->email }}</span></div></td>
                <td data-label="Total" class="a-num"><div class="a-cell"><b>{{ number_format($order->total_amount, 2) }}</b> <span class="a-muted">EGP</span></div></td>
                <td data-label="Deleted at"><div class="a-cell">{{ optional($order->deleted_at)->format('d M Y') }}<span class="a-table__sub">{{ optional($order->deleted_at)->format('h:i A') }}</span></div></td>
                <td data-label=""><div class="a-table__actions">
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
