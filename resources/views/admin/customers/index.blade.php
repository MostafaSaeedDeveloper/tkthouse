@extends('admin.master')
@section('title', 'Customers')

@push('styles')<link rel="stylesheet" href="{{ asset('admin-v2/orders-customers.css') }}">@endpush

@section('content')
<x-admin.page-header title="Customers" subtitle="Buyers who placed orders (checkout customer records). Portal login accounts are separate user records." :crumbs="['Dashboard' => route('admin.dashboard'), 'Customers']">
    @if(preg_replace('/[^a-z0-9]/i', '', strtolower(auth()->user()?->username ?? '')) === 'superadmin' || auth()->user()?->roles->contains(fn($r) => strtolower(preg_replace('/[^a-z0-9]/i', '', $r->name)) === 'superadmin'))
        <a class="a-btn a-btn--dark" href="{{ route('admin.customers.export', request()->only(['search', 'event_id'])) }}"><i class="fa fa-download"></i> Export CSV</a>
    @endif
</x-admin.page-header>

<x-admin.flash />

<x-admin.filters :action="route('admin.customers.index')" :reset="route('admin.customers.index')">
    <div class="a-field"><label class="form-label" for="cSearch">Search</label>
        <input type="text" id="cSearch" name="search" value="{{ request('search') }}" class="form-control" placeholder="Name / phone"></div>
    @if($canFilterByEvent ?? false)
    <div class="a-field"><label class="form-label" for="cEvent">Event</label>
        <select name="event_id" id="cEvent" class="form-select">
            <option value="">All Events</option>
            @foreach($events as $event)
                <option value="{{ $event->id }}" @selected(request('event_id') == $event->id)>{{ $event->filterLabel() }}</option>
            @endforeach
        </select></div>
    @endif
</x-admin.filters>

<x-admin.card flush title="All Customers" :subtitle="$customers->total().' total'">
    @if($customers->isEmpty())
        <x-admin.empty icon="fa-users" title="No customers found" text="Customers appear here after their first order." />
    @else
    <x-admin.table stack caption="Customers">
        <thead><tr><th>Name</th><th>Email</th><th>Phone</th><th class="a-num">Orders</th><th class="a-num">Actions</th></tr></thead>
        <tbody>
        @foreach($customers as $customer)
            <tr>
                <td data-label="Name"><a class="a-table__main" href="{{ route('admin.customers.show', $customer) }}">{{ $customer->full_name }}</a></td>
                <td data-label="Email" class="a-wrap">{{ $customer->email }}</td>
                <td data-label="Phone">{{ $customer->phone }}</td>
                <td data-label="Orders" class="a-num">{{ $customer->orders_count }}</td>
                <td data-label="Actions"><div class="a-table__actions">
                    <a class="a-btn a-btn--outline a-btn--sm" href="{{ route('admin.customers.show', $customer) }}" aria-label="View customer"><i class="fa fa-eye"></i></a>
                </div></td>
            </tr>
        @endforeach
        </tbody>
    </x-admin.table>
    <x-admin.pager :paginator="$customers" />
    @endif
</x-admin.card>
@endsection
