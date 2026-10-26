@extends('admin.master')
@section('title', 'Customers')

@push('styles')<link rel="stylesheet" href="{{ asset('admin-v2/orders-customers.css') }}">@endpush

@section('content')
@php
    $canExport = preg_replace('/[^a-z0-9]/i', '', strtolower(auth()->user()?->username ?? '')) === 'superadmin' || auth()->user()?->roles->contains(fn($r) => strtolower(preg_replace('/[^a-z0-9]/i', '', $r->name)) === 'superadmin');
@endphp
<x-admin.page-header title="Customers" subtitle="Buyers who placed orders (checkout customer records). Portal login accounts are separate user records." :crumbs="['Dashboard' => route('admin.dashboard'), 'Customers']">
    @if($canExport)
        <button type="button" class="a-btn a-btn--dark" data-bs-toggle="modal" data-bs-target="#exportCustomersModal"><i class="fa fa-download"></i> Export CSV</button>
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
    <div class="a-field"><label class="form-label" for="cStatus">Order status</label>
        <select name="status" id="cStatus" class="form-select">
            <option value="">All Statuses</option>
            @foreach($statusOptions as $value => $label)
                <option value="{{ $value }}" @selected(request('status') === $value)>{{ $label }}</option>
            @endforeach
        </select></div>
</x-admin.filters>

<x-admin.card flush title="All Customers" :subtitle="$customers->total().' total'">
    @if($customers->isEmpty())
        <x-admin.empty icon="fa-users" title="No customers found" text="Customers appear here after their first order." />
    @else
    @if($canExport)
    <div class="a-ord-bulk" id="custBulkBar" hidden>
        <span class="a-ord-bulk__count"><strong data-cust-count>0</strong> selected</span>
        <button type="button" class="a-btn a-btn--dark a-btn--sm" data-bs-toggle="modal" data-bs-target="#exportCustomersModal"><i class="fa fa-download"></i> Export selected</button>
        <button type="button" class="a-btn a-btn--outline a-btn--sm" id="custClear">Clear</button>
    </div>
    @endif
    <x-admin.table stack class="a-ordt" caption="Customers">
        <thead><tr>@if($canExport)<th scope="col" class="c-check"><input type="checkbox" class="a-ord-check" id="custAll" aria-label="Select all customers on this page"></th>@endif<th>Name</th><th>Email</th><th>Phone</th><th class="a-num">Orders</th><th class="a-num">Actions</th></tr></thead>
        <tbody>
        @foreach($customers as $customer)
            <tr>
                @if($canExport)<td class="c-check" data-label=""><input type="checkbox" class="a-ord-check cust-check" value="{{ $customer->id }}" aria-label="Select {{ $customer->full_name }}"></td>@endif
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

@if($canExport)
<div class="modal fade" id="exportCustomersModal" tabindex="-1" aria-labelledby="exportCustomersLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <form method="POST" action="{{ route('admin.customers.export') }}" id="exportCustomersForm">
                @csrf
                <input type="hidden" name="search" value="{{ request('search') }}">
                <input type="hidden" name="event_id" value="{{ request('event_id') }}">
                <input type="hidden" name="status" value="{{ request('status') }}">
                <div class="modal-header">
                    <h5 class="modal-title" id="exportCustomersLabel"><i class="fa fa-download me-2"></i>Export Customers to CSV</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <p class="mb-3" id="custExportScope">Exporting all customers matching the current filters (search, event, order status).</p>
                    <div id="custExportIds"></div>
                    <h6 class="fw-semibold text-muted text-uppercase fs-sm mb-2">Columns to Export</h6>
                    <div class="a-ord-export-cols">
                        @foreach($exportColumns as $colKey => $col)
                        <div class="form-check">
                            <input class="form-check-input cust-col-check" type="checkbox" name="columns[]" value="{{ $colKey }}" id="ccol_{{ $colKey }}" checked>
                            <label class="form-check-label" for="ccol_{{ $colKey }}">{{ $col['label'] }}</label>
                        </div>
                        @endforeach
                    </div>
                    <div class="mt-2 d-flex gap-2">
                        <button type="button" class="a-btn a-btn--outline a-btn--sm" id="custColsAll">Select All</button>
                        <button type="button" class="a-btn a-btn--outline a-btn--sm" id="custColsNone">Deselect All</button>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="a-btn a-btn--outline" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="a-btn a-btn--primary"><i class="fa fa-download me-1"></i> Download CSV</button>
                </div>
            </form>
        </div>
    </div>
</div>
<script>
(function () {
    var all = document.getElementById('custAll'), bar = document.getElementById('custBulkBar');
    var boxes = function () { return Array.prototype.slice.call(document.querySelectorAll('.cust-check')); };
    var checked = function () { return boxes().filter(function (b) { return b.checked; }); };
    function sync() {
        var n = checked().length;
        if (bar) { bar.hidden = n === 0; bar.querySelector('[data-cust-count]').textContent = n; }
        if (all) { all.checked = n > 0 && n === boxes().length; all.indeterminate = n > 0 && n < boxes().length; }
        document.getElementById('custExportScope').textContent = n
            ? 'Exporting ' + n + ' selected customer' + (n === 1 ? '' : 's') + '.'
            : 'Exporting all customers matching the current filters (search, event, order status).';
        var holder = document.getElementById('custExportIds');
        holder.innerHTML = '';
        checked().forEach(function (b) {
            var i = document.createElement('input'); i.type = 'hidden'; i.name = 'ids[]'; i.value = b.value; holder.appendChild(i);
        });
    }
    all && all.addEventListener('change', function () { boxes().forEach(function (b) { b.checked = all.checked; }); sync(); });
    document.addEventListener('change', function (e) { if (e.target.matches('.cust-check')) sync(); });
    document.getElementById('custClear')?.addEventListener('click', function () { boxes().forEach(function (b) { b.checked = false; }); sync(); });
    document.getElementById('custColsAll').addEventListener('click', function () { document.querySelectorAll('.cust-col-check').forEach(function (c) { c.checked = true; }); });
    document.getElementById('custColsNone').addEventListener('click', function () { document.querySelectorAll('.cust-col-check').forEach(function (c) { c.checked = false; }); });
    sync();
})();
</script>
@endif
@endsection
