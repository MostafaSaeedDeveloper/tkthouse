@extends('admin.master')
@section('title', 'Customers')


@section('content')
<x-admin.page-header title="Customers" subtitle="Buyers who placed orders (checkout customer records). Portal login accounts are separate user records." :crumbs="['Dashboard' => route('admin.dashboard'), 'Customers']">
    @if(preg_replace('/[^a-z0-9]/i', '', strtolower(auth()->user()?->username ?? '')) === 'superadmin' || auth()->user()?->roles->contains(fn($r) => strtolower(preg_replace('/[^a-z0-9]/i', '', $r->name)) === 'superadmin'))
        <a class="a-btn a-btn--dark" href="{{ route('admin.customers.export', request()->only(['search', 'event_id'])) }}"><i class="fa fa-download"></i> Export CSV</a>
    @endif
</x-admin.page-header>

<x-admin.flash />

<x-admin.filters :action="route('admin.customers.index')" :reset="route('admin.customers.index')">
    <x-admin.search name="search" :value="request('search')" placeholder="Name / phone" />
    @if($canFilterByEvent ?? false)
        <x-admin.select label="Event" name="event_id" id="cEvent" class="no-select2" placeholder="All Events" :value="request('event_id')" :options="$events->mapWithKeys(fn ($e) => [$e->id => $e->name])->all()" />
    @endif
</x-admin.filters>

<x-admin.card flush title="All Customers" :subtitle="$customers->total().' total'">
    @if($customers->isEmpty())
        <x-admin.empty icon="fa-users" title="No customers found" text="Customers appear here after their first order." />
    @else
    <x-admin.table stack caption="Customers">
        <thead><tr><th>Customer</th><th>Phone</th><th class="a-num">Orders</th><th class="a-num"><span class="a-sr-only">Actions</span></th></tr></thead>
        <tbody>
        @foreach($customers as $customer)
            <tr>
                <td data-label="Customer"><div class="a-cell"><a class="a-table__main a-trunc" href="{{ route('admin.customers.show', $customer) }}" title="{{ $customer->full_name }}">{{ $customer->full_name }}</a><span class="a-table__sub a-trunc" title="{{ $customer->email }}">{{ $customer->email }}</span></div></td>
                <td data-label="Phone"><div class="a-cell">{{ $customer->phone ?: '—' }}</div></td>
                <td data-label="Orders" class="a-num"><div class="a-cell">{{ $customer->orders_count }}</div></td>
                <td data-label=""><div class="a-table__actions">
                    <a class="a-btn a-btn--outline a-btn--sm" href="{{ route('admin.customers.show', $customer) }}">View</a>
                </div></td>
            </tr>
        @endforeach
        </tbody>
    </x-admin.table>
    <x-admin.pager :paginator="$customers" />
    @endif
</x-admin.card>
@endsection
