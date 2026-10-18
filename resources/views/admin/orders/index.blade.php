@extends('admin.master')
@section('title', 'Orders')

@push('styles')<link rel="stylesheet" href="{{ asset('admin-v2/orders-customers.css') }}?v={{ filemtime(public_path('admin-v2/orders-customers.css')) }}">@endpush

@section('content')
@php
    $sortUrl = function (string $key) use ($sortKey, $sortDir) {
        $dir = ($sortKey === $key && $sortDir === 'desc') ? 'asc' : 'desc';
        return request()->fullUrlWithQuery(['sort' => $key, 'dir' => $dir, 'page' => null]);
    };
    $ariaSort = fn (string $key) => $sortKey === $key ? ($sortDir === 'asc' ? 'ascending' : 'descending') : 'none';
    $sortIcon = fn (string $key) => $sortKey === $key ? ($sortDir === 'asc' ? 'fa-arrow-up' : 'fa-arrow-down') : 'fa-sort';
    $chipUrl = fn (?string $group) => request()->fullUrlWithQuery(['group' => $group, 'status' => null, 'page' => null]);
    $activeGroup = in_array(request('group'), ['pending', 'paid', 'canceled'], true) ? request('group') : null;
    $rangeFrom = preg_match('/^\d{4}-\d{2}-\d{2}$/', (string) request('date_from')) ? \Illuminate\Support\Carbon::parse(request('date_from')) : null;
    $rangeTo = preg_match('/^\d{4}-\d{2}-\d{2}$/', (string) request('date_to')) ? \Illuminate\Support\Carbon::parse(request('date_to')) : null;
    $rangeLabel = match (true) {
        $rangeFrom && $rangeTo => $rangeFrom->format('d M Y').' – '.$rangeTo->format('d M Y'),
        (bool) $rangeFrom => 'From '.$rangeFrom->format('d M Y'),
        (bool) $rangeTo => 'Until '.$rangeTo->format('d M Y'),
        default => 'All dates',
    };
@endphp
<x-admin.page-header title="Orders" subtitle="Search, filter and manage every order." :crumbs="['Dashboard' => route('admin.dashboard'), 'Orders']">
    @can('orders.deleted.view')
        <a class="a-btn a-btn--outline" href="{{ route('admin.orders.deleted') }}"><i class="fa fa-trash-can"></i> Deleted Orders <x-admin.badge variant="dark">{{ $deletedOrdersCount ?? 0 }}</x-admin.badge></a>
    @endcan
    @if($canExport)
        <button type="button" class="a-btn a-btn--dark" data-bs-toggle="modal" data-bs-target="#exportOrdersModal"><i class="fa fa-download"></i> Export CSV</button>
    @endif
</x-admin.page-header>

<x-admin.flash />

<section class="a-ord-summary" aria-label="Orders summary">
    <dl class="a-ord-summary__grid">
        <div><dt>All orders</dt><dd>{{ number_format($summary['all']) }}</dd></div>
        <div><dt>Pending</dt><dd>{{ number_format($summary['pending']) }}</dd><small>Approval, payment or on hold</small></div>
        <div><dt>Paid</dt><dd>{{ number_format($summary['paid']) }}</dd><small>{{ number_format($summary['paid_amount'], 2) }} EGP</small></div>
        <div><dt>Canceled</dt><dd>{{ number_format($summary['canceled']) }}</dd><small>Canceled or rejected</small></div>
    </dl>
    <p class="a-ord-summary__scope"><i class="fa fa-calendar" aria-hidden="true"></i> {{ $rangeLabel }} <span aria-hidden="true">·</span> follows your search and filters</p>
</section>

<nav class="a-ord-quick" aria-label="Quick status filters">
    <a href="{{ $chipUrl(null) }}" class="a-chip {{ $activeGroup === null ? 'is-active' : '' }}" @if($activeGroup === null) aria-current="true" @endif>All <span>{{ number_format($summary['all']) }}</span></a>
    <a href="{{ $chipUrl('pending') }}" class="a-chip {{ $activeGroup === 'pending' ? 'is-active' : '' }}" @if($activeGroup === 'pending') aria-current="true" @endif>Pending <span>{{ number_format($summary['pending']) }}</span></a>
    <a href="{{ $chipUrl('paid') }}" class="a-chip {{ $activeGroup === 'paid' ? 'is-active' : '' }}" @if($activeGroup === 'paid') aria-current="true" @endif>Paid <span>{{ number_format($summary['paid']) }}</span></a>
    <a href="{{ $chipUrl('canceled') }}" class="a-chip {{ $activeGroup === 'canceled' ? 'is-active' : '' }}" @if($activeGroup === 'canceled') aria-current="true" @endif>Canceled <span>{{ number_format($summary['canceled']) }}</span></a>
</nav>

@include('admin.orders.partials.filters', ['action' => route('admin.orders.index'), 'reset' => route('admin.orders.index'), 'idp' => 'o'])

@php $canBulk = $canAssign || auth()->user()->can('orders.update'); @endphp
<x-admin.card flush class="a-ord-listcard" title="Orders" :subtitle="number_format($orders->total()).' matching'">
    @if($orders->isEmpty())
        <x-admin.empty icon="fa-receipt" title="No orders found" text="No order matches the current search and filters.">
            @if(request()->hasAny(['search','status','group','payment_method','event_id','reference','customer','date_from','date_to']))
                <a class="a-btn a-btn--outline" href="{{ route('admin.orders.index') }}">Clear filters</a>
            @endif
        </x-admin.empty>
    @else
    @if($canBulk)
    <form method="POST" action="{{ route('admin.orders.assign.bulk') }}" id="bulkAssignForm" class="a-ord-bulk" hidden>
        @csrf
        <span class="a-ord-bulk__count"><strong data-bulk-count>0</strong> selected</span>
        @if($canAssign)
        <span class="a-ord-bulk__assign">
        <label class="a-sr-only" for="bulkAssignee">Assign selected orders to</label>
        <select id="bulkAssignee" class="form-select" name="assignee_id"><option value="">Unassigned</option>@foreach($assignees as $a)<option value="{{ $a->id }}">{{ $a->name }}</option>@endforeach</select>
        <input class="form-control" name="reason" maxlength="300" placeholder="Reason (optional)" aria-label="Reason">
        <button type="submit" class="a-btn a-btn--primary a-btn--sm">Assign</button>
        </span>
        @endif
        @can('orders.update')
        <span class="a-ord-bulk__decide">
            <button type="submit" class="a-btn a-btn--success a-btn--sm" formaction="{{ route('admin.orders.bulk.approve') }}" data-bulk-confirm="Approve the selected orders that are awaiting approval and email each customer a payment link? Other orders are skipped."><i class="fa fa-check" aria-hidden="true"></i> Approve</button>
            <button type="submit" class="a-btn a-btn--danger a-btn--sm" formaction="{{ route('admin.orders.bulk.reject') }}" data-bulk-confirm="Reject the selected orders that are awaiting approval and email each customer? Other orders are skipped."><i class="fa fa-xmark" aria-hidden="true"></i> Reject</button>
        </span>
        @endcan
    </form>
    @endif
    <x-admin.table stack class="a-ordt" caption="Orders">
        <thead>
            <tr>
                @if($canBulk)<th scope="col" class="c-check"><input type="checkbox" class="a-ord-check" data-bulk-all aria-label="Select all orders on this page" form="bulkAssignForm"></th>@endif
                <th scope="col" class="c-order" aria-sort="{{ $ariaSort('created_at') }}"><a class="a-ordt__sort" href="{{ $sortUrl('created_at') }}">Order <i class="fa {{ $sortIcon('created_at') }}" aria-hidden="true"></i></a></th>
                <th scope="col" class="c-customer">Customer</th>
                <th scope="col" class="c-event">Event</th>
                <th scope="col" class="c-tickets">Tickets</th>
                @if($assignmentOn)<th scope="col" class="c-assignee">Assigned to</th>@endif
                <th scope="col" class="c-total a-num" aria-sort="{{ $ariaSort('total_amount') }}"><a class="a-ordt__sort" href="{{ $sortUrl('total_amount') }}">Total <i class="fa {{ $sortIcon('total_amount') }}" aria-hidden="true"></i></a></th>
                <th scope="col" class="c-status" aria-sort="{{ $ariaSort('status') }}"><a class="a-ordt__sort" href="{{ $sortUrl('status') }}">Status <i class="fa {{ $sortIcon('status') }}" aria-hidden="true"></i></a></th>
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
                $ticketTypes = \App\Support\OrderPresenter::ticketTypes($order);
                $secondsLeft = $order->paymentSecondsRemaining();
                $attempt = $order->latestPaymentAttempt;
                $showAttempt = $attempt && ($order->status !== 'paid' || $attempt->status !== 'paid');
                $methodLabel = $order->payment_method ? ($paymentMethodLabels[$order->payment_method] ?? ucwords(str_replace('_', ' ', $order->payment_method))) : null;
                $customerName = $order->customer?->full_name ?: 'Unknown customer';
            @endphp
            <tr>
                @if($canBulk)<td class="c-check" data-label=""><input type="checkbox" class="a-ord-check" name="order_ids[]" value="{{ $order->id }}" form="bulkAssignForm" aria-label="Select order {{ $number }}"></td>@endif
                <td data-label="Order" class="c-order"><a class="a-table__main" href="{{ route('admin.orders.show', $order) }}">#{{ $number }}</a>
                    <span class="a-table__sub">{{ $order->created_at?->format('d-m-Y h:i A') }}</span></td>
                <td data-label="Customer" class="c-customer"><span class="a-table__main a-ord-ellipsis" title="{{ $customerName }}">{{ $customerName }}</span><span class="a-table__sub a-ord-ellipsis" title="{{ $order->customer?->email }}">{{ $order->customer?->email }}</span></td>
                <td data-label="Event" class="c-event">
                    @if($eventNames->isEmpty())
                        <span class="a-muted">—</span>
                    @else
                        <span class="a-ord-clamp" title="{{ $eventNames->implode(' · ') }}">{{ $eventNames->first() }}</span>
                        @if($eventNames->count() > 1)<span class="a-table__sub">+{{ $eventNames->count() - 1 }} more {{ \Illuminate\Support\Str::plural('event', $eventNames->count() - 1) }}</span>@endif
                    @endif
                </td>
                <td data-label="Tickets" class="c-tickets">
                    <div class="a-ord-tix"><span class="a-table__main">{{ $ticketCount }}</span><span class="a-ord-tix__label">{{ \Illuminate\Support\Str::plural('ticket', $ticketCount) }}</span>
                    @if($ticketTypes->isNotEmpty())
                        <button type="button" class="a-btn a-btn--outline a-btn--sm a-ord-tixbtn" data-ord-tix-open data-number="{{ $number }}" data-types="{{ json_encode($ticketTypes->all()) }}" aria-haspopup="dialog" title="View tickets" aria-label="View tickets of order {{ $number }}"><i class="fa fa-ticket" aria-hidden="true"></i></button>
                    @endif</div>
                </td>
                @if($assignmentOn)
                <td data-label="Assigned to" class="c-assignee">
                    @if($order->assignedUser)<span class="a-table__main a-ord-ellipsis" title="{{ $order->assignedUser->name }}">{{ $order->assignedUser->name }}</span>
                    @else<x-admin.badge variant="warning">Unassigned</x-admin.badge>@endif
                </td>
                @endif
                <td data-label="Total" class="c-total a-num"><span class="a-table__main">{{ number_format((float) $order->total_amount, 2) }}</span><span class="a-table__sub">EGP</span></td>
                <td data-label="Status" class="c-status">
                    <x-admin.status :status="$order->status" kind="order" />
                    @if($showAttempt)<span class="a-ord-pay">Payment: <x-admin.status :status="$attempt->status" kind="payment" /></span>@endif
                    @if($methodLabel)<span class="a-table__sub">{{ $methodLabel }}</span>@endif
                    @if($order->status === 'pending_payment' && $secondsLeft !== null)
                        <span class="a-table__sub a-ord-timer js-admin-order-timer" data-seconds-left="{{ max(0, $secondsLeft) }}">{{ $secondsLeft > 0 ? 'Expires soon' : 'Expired' }}</span>
                    @endif
                </td>
                <td data-label="" class="c-actions"><div class="a-table__actions">
                    @can('orders.update')
                        @if($order->status === 'pending_approval')
                            <form method="POST" action="{{ route('admin.orders.approve', $order) }}" data-a-confirm="Approve this order and send the payment link?" data-a-confirm-title="Approve order" data-a-confirm-ok="Approve">@csrf
                                <button class="a-btn a-btn--success a-btn--sm" type="submit" title="Approve" aria-label="Approve order {{ $number }}"><i class="fa fa-check"></i><span class="a-ord-btn-text">Approve</span></button>
                            </form>
                        @endif
                    @endcan
                    <a class="a-btn a-btn--outline a-btn--sm" href="{{ route('admin.orders.show', $order) }}" title="View" aria-label="View order {{ $number }}"><i class="fa fa-eye"></i><span class="a-ord-btn-text">View</span></a>
                    @if(\App\Orders\OrderAccess::canManage($order, auth()->user()) || $canAssign)
                    @canany(['orders.update', 'update_status', 'orders.delete'])
                    <div class="a-menu a-menu--fixed">
                        <button type="button" class="a-btn a-btn--outline a-btn--sm" data-a-menu-toggle aria-haspopup="true" aria-expanded="false" aria-label="More actions for order {{ $number }}" title="More actions"><i class="fa fa-ellipsis-vertical"></i></button>
                        <div class="a-menu__panel" role="menu">
                            @can('orders.update')
                                <a class="a-menu__item" role="menuitem" href="{{ route('admin.orders.edit', $order) }}"><i class="fa fa-pen" aria-hidden="true"></i> Edit order</a>
                            @endcan
                            @if($canAssign)
                                <button type="button" class="a-menu__item" role="menuitem" data-ord-assign-open data-action="{{ route('admin.orders.assign', $order) }}" data-current="{{ $order->assigned_user_id }}" data-number="{{ $number }}"><i class="fa fa-user-check" aria-hidden="true"></i> {{ $order->assigned_user_id ? 'Reassign' : 'Assign' }}</button>
                            @endif
                            @can('update_status')
                                <button type="button" class="a-menu__item" role="menuitem" data-ord-status-open data-action="{{ route('admin.orders.status.update', $order) }}" data-current="{{ $order->status }}" data-number="{{ $number }}"><i class="fa fa-arrows-rotate" aria-hidden="true"></i> Change status</button>
                            @endcan
                            @can('orders.update')
                                @if($order->status === 'pending_approval')
                                    <form method="POST" action="{{ route('admin.orders.reject', $order) }}" data-a-confirm="Reject this order?" data-a-confirm-title="Reject order" data-a-confirm-ok="Reject">@csrf
                                        <button type="submit" class="a-menu__item a-menu__item--danger" role="menuitem"><i class="fa fa-xmark" aria-hidden="true"></i> Reject order</button>
                                    </form>
                                @endif
                            @endcan
                            @can('orders.delete')
                                <form method="POST" action="{{ route('admin.orders.destroy', $order) }}" data-a-confirm="Delete this order? It can be restored from Deleted Orders." data-a-confirm-title="Delete order" data-a-confirm-ok="Delete">@csrf @method('DELETE')
                                    <button type="submit" class="a-menu__item a-menu__item--danger" role="menuitem"><i class="fa fa-trash" aria-hidden="true"></i> Delete order</button>
                                </form>
                            @endcan
                        </div>
                    </div>
                    @endcanany
                    @endif
                </div></td>
            </tr>
        @endforeach
        </tbody>
    </x-admin.table>
    <x-admin.pager :paginator="$orders" />
    @endif
</x-admin.card>

@if($canAssign)
<div class="a-ord-modal" id="ordAssignModal" aria-hidden="true">
    <div class="a-ord-modal__box" role="dialog" aria-modal="true" aria-labelledby="ordAssignTitle">
        <h2 id="ordAssignTitle">Assign order · <span id="ordAssignNumber"></span></h2>
        <form method="POST" action="#" id="ordAssignForm" data-a-loading>
            @csrf
            <label class="form-label" for="ordAssignSelect">Assigned to</label>
            <select id="ordAssignSelect" class="form-select" name="assignee_id">
                <option value="">Unassigned</option>
                @foreach($assignees as $a)<option value="{{ $a->id }}">{{ $a->name }}</option>@endforeach
            </select>
            <label class="form-label" for="ordAssignReason" style="margin-top:12px">Reason (optional)</label>
            <input id="ordAssignReason" class="form-control" name="reason" maxlength="300">
            <p class="a-hint">Changing the assignee never changes how this order is paid.</p>
            <div class="a-btn-group" style="justify-content:flex-end;margin-top:16px">
                <button type="button" class="a-btn a-btn--outline" data-ord-assign-close>Cancel</button>
                <button type="submit" class="a-btn a-btn--primary">Save</button>
            </div>
        </form>
    </div>
</div>
@endif

@can('update_status')
<div class="a-ord-modal" id="ordStatusModal" aria-hidden="true">
    <div class="a-ord-modal__box" role="dialog" aria-modal="true" aria-labelledby="ordStatusTitle">
        <h2 id="ordStatusTitle">Change status · <span id="ordStatusNumber"></span></h2>
        <form method="POST" action="#" id="ordStatusForm" data-a-loading>
            @csrf
            <label class="form-label" for="ordStatusSelect">Order status</label>
            <select id="ordStatusSelect" class="form-select" name="status">
                @foreach($statusOptions as $value => $label)<option value="{{ $value }}">{{ $label }}</option>@endforeach
            </select>
            <div class="a-btn-group" style="justify-content:flex-end;margin-top:16px">
                <button type="button" class="a-btn a-btn--outline" data-ord-status-close>Cancel</button>
                <button type="submit" class="a-btn a-btn--primary">Save status</button>
            </div>
        </form>
    </div>
</div>
@endcan

<div class="a-ord-modal" id="ordTixModal" aria-hidden="true">
    <div class="a-ord-modal__box" role="dialog" aria-modal="true" aria-labelledby="ordTixTitle">
        <h2 id="ordTixTitle">Tickets · #<span id="ordTixNumber"></span></h2>
        <ul class="a-ord-tixlist" id="ordTixList"></ul>
        <div class="a-ord-tixtotal"><span><b id="ordTixCount"></b> tickets</span><span id="ordTixSum"></span></div>
        <div class="a-btn-group" style="justify-content:flex-end;margin-top:16px">
            <button type="button" class="a-btn a-btn--outline" data-ord-tix-close>Close</button>
        </div>
    </div>
</div>

@if($canBulk)
<script src="{{ asset('admin-v2/orders-assign.js') }}?v={{ filemtime(public_path('admin-v2/orders-assign.js')) }}" defer></script>
@endif
<script src="{{ asset('admin-v2/orders-list.js') }}?v={{ filemtime(public_path('admin-v2/orders-list.js')) }}" defer></script>

{{-- Export Orders Modal --}}
<div class="modal fade" id="exportOrdersModal" tabindex="-1" aria-labelledby="exportOrdersModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <form method="GET" action="{{ route('admin.orders.export') }}" id="exportOrdersForm">
                <div class="modal-header">
                    <h5 class="modal-title" id="exportOrdersModalLabel"><i class="fa fa-download me-2"></i>Export Orders to CSV</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">

                    {{-- Filters --}}
                    <h6 class="fw-semibold text-muted text-uppercase fs-sm mb-2">Filters</h6>
                    <div class="row g-3 mb-4">
                        <div class="col-md-6">
                            <label class="form-label">Search (Order # / Customer)</label>
                            <input type="text" name="search" class="form-control" placeholder="Order # / customer name / email">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Status</label>
                            <select name="status" class="form-select" id="exportStatusSelect">
                                <option value="">All Statuses</option>
                                @foreach($statusOptions as $s => $statusLabel)
                                    <option value="{{ $s }}">{{ $statusLabel }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Payment Method</label>
                            <select name="payment_method" class="form-select" id="exportPaymentSelect">
                                <option value="">All Payment Methods</option>
                                @foreach($paymentMethods as $method)
                                    <option value="{{ $method->code }}">{{ $method->checkout_label ?: $method->name }}</option>
                                @endforeach
                            </select>
                        </div>
                        @if($canFilterByEvent)
                        <div class="col-md-6">
                            <label class="form-label">Event</label>
                            <select name="event_id" class="form-select" id="exportEventSelect">
                                <option value="">All Events</option>
                                @foreach($events as $event)
                                    <option value="{{ $event->id }}">{{ $event->name }}</option>
                                @endforeach
                            </select>
                        </div>
                        @endif
                    </div>

                    {{-- Columns --}}
                    <h6 class="fw-semibold text-muted text-uppercase fs-sm mb-2">Columns to Export</h6>
                    <div class="a-ord-export-cols">
                        @php
                            $exportColumns = [
                                'order_number'   => 'Order #',
                                'customer_name'  => 'Customer Name',
                                'customer_email' => 'Customer Email',
                                'customer_phone' => 'Customer Phone',
                                'event'          => 'Event',
                                'ticket_types'   => 'Ticket Types',
                                'items_count'    => 'Items Count',
                                'total'          => 'Total Amount',
                                'status'         => 'Status',
                                'payment_method' => 'Payment Method',
                                'created_at'     => 'Created At',
                                'paid_at'        => 'Paid At',
                            ];
                        @endphp
                        @foreach($exportColumns as $colKey => $colLabel)
                        <div>
                            <div class="form-check">
                                <input class="form-check-input export-col-check" type="checkbox" name="columns[]" value="{{ $colKey }}" id="col_{{ $colKey }}" checked>
                                <label class="form-check-label" for="col_{{ $colKey }}">{{ $colLabel }}</label>
                            </div>
                        </div>
                        @endforeach
                    </div>
                    <div class="mt-2 d-flex gap-2">
                        <button type="button" class="a-btn a-btn--outline a-btn--sm" id="selectAllCols">Select All</button>
                        <button type="button" class="a-btn a-btn--outline a-btn--sm" id="deselectAllCols">Deselect All</button>
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
document.getElementById('selectAllCols')?.addEventListener('click', () => {
    document.querySelectorAll('.export-col-check').forEach(c => c.checked = true);
});
document.getElementById('deselectAllCols')?.addEventListener('click', () => {
    document.querySelectorAll('.export-col-check').forEach(c => c.checked = false);
});

</script>
@endsection
