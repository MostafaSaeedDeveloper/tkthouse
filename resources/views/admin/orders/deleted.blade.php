@extends('admin.master')
@section('title', 'Deleted Orders')

@push('styles')<link rel="stylesheet" href="{{ asset('admin-v2/orders-customers.css') }}?v={{ filemtime(public_path('admin-v2/orders-customers.css')) }}">@endpush

@section('content')
<x-admin.page-header title="Deleted Orders" subtitle="Soft-deleted orders can be restored from here." :crumbs="['Dashboard' => route('admin.dashboard'), 'Orders' => route('admin.orders.index'), 'Deleted']">
    <a class="a-btn a-btn--outline" href="{{ route('admin.orders.index') }}"><i class="fa fa-arrow-left"></i> Back to Orders</a>
</x-admin.page-header>

<x-admin.flash />

@include('admin.orders.partials.filters', ['action' => route('admin.orders.deleted'), 'reset' => route('admin.orders.deleted'), 'idp' => 'd'])

<x-admin.card flush class="a-ord-listcard" title="Trash" :subtitle="number_format($orders->total()).' deleted'">
    @if($orders->isEmpty())
        <x-admin.empty icon="fa-trash-can" title="No deleted orders found" text="Deleted orders will appear here and can be restored.">
            @if(request()->hasAny(['search','status','payment_method','event_id','reference','customer','date_from','date_to']))
                <a class="a-btn a-btn--outline" href="{{ route('admin.orders.deleted') }}">Clear filters</a>
            @endif
        </x-admin.empty>
    @else
    <x-admin.table stack class="a-ordt a-ordt--trash" caption="Deleted orders">
        <thead>
            <tr>
                <th scope="col" class="c-order">Order</th>
                <th scope="col" class="c-customer">Customer</th>
                <th scope="col" class="c-event">Event</th>
                <th scope="col" class="c-tickets a-num">Tickets</th>
                <th scope="col" class="c-total a-num">Total</th>
                <th scope="col" class="c-status">Status</th>
                <th scope="col" class="c-deleted">Deleted</th>
                <th scope="col" class="c-actions"><span class="a-sr-only">Actions</span></th>
            </tr>
        </thead>
        <tbody>
        @foreach($orders as $order)
            @php
                $number = \App\Support\OrderPresenter::number($order);
                $eventNames = \App\Support\OrderPresenter::eventNames($order);
                $ticketCount = \App\Support\OrderPresenter::ticketCount($order);
                $typeCount = \App\Support\OrderPresenter::ticketTypeCount($order);
                $customerName = $order->customer?->full_name ?: 'Unknown customer';
            @endphp
            <tr>
                <td data-label="Order" class="c-order"><span class="a-table__main">#{{ $number }}</span><span class="a-table__sub">{{ $order->created_at?->format('d M Y, H:i') }}</span></td>
                <td data-label="Customer" class="c-customer"><span class="a-table__main a-ord-ellipsis" title="{{ $customerName }}">{{ $customerName }}</span><span class="a-table__sub a-ord-ellipsis" title="{{ $order->customer?->email }}">{{ $order->customer?->email }}</span></td>
                <td data-label="Event" class="c-event">
                    @if($eventNames->isEmpty())<span class="a-muted">—</span>@else
                        <span class="a-ord-clamp" title="{{ $eventNames->implode(' · ') }}">{{ $eventNames->first() }}</span>
                        @if($eventNames->count() > 1)<span class="a-table__sub">+{{ $eventNames->count() - 1 }} more {{ \Illuminate\Support\Str::plural('event', $eventNames->count() - 1) }}</span>@endif
                    @endif
                    <span class="a-table__sub a-ord-inline-tix">{{ $ticketCount }} {{ \Illuminate\Support\Str::plural('ticket', $ticketCount) }}@if($typeCount > 1) · {{ $typeCount }} types @endif</span>
                </td>
                <td data-label="Tickets" class="c-tickets a-num"><span class="a-table__main">{{ $ticketCount }}</span>@if($typeCount > 1)<span class="a-table__sub">{{ $typeCount }} types</span>@endif</td>
                <td data-label="Total" class="c-total a-num"><span class="a-table__main">{{ number_format((float) $order->total_amount, 2) }}</span><span class="a-table__sub">EGP</span></td>
                <td data-label="Status" class="c-status"><x-admin.status :status="$order->status" kind="order" />
                    @if($order->payment_method)<span class="a-table__sub">{{ $paymentMethodLabels[$order->payment_method] ?? ucwords(str_replace('_', ' ', $order->payment_method)) }}</span>@endif</td>
                <td data-label="Deleted" class="c-deleted"><span class="a-table__main">{{ optional($order->deleted_at)->format('d M Y') }}</span><span class="a-table__sub">{{ optional($order->deleted_at)->format('h:i A') }}</span></td>
                <td data-label="" class="c-actions"><div class="a-table__actions">
                    @can('orders.restore')
                        <form method="POST" action="{{ route('admin.orders.restore', $order->id) }}" data-a-confirm="Restore this order?" data-a-confirm-title="Restore order" data-a-confirm-ok="Restore">
                            @csrf
                            <button class="a-btn a-btn--success a-btn--sm" type="submit" aria-label="Restore order {{ $number }}"><i class="fa fa-rotate-left"></i> Restore</button>
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
<script src="{{ asset('admin-v2/orders-list.js') }}?v={{ filemtime(public_path('admin-v2/orders-list.js')) }}" defer></script>
@endsection
