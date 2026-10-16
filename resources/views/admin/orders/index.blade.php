@extends('admin.master')
@section('title', 'Orders')

@push('styles')<link rel="stylesheet" href="{{ asset('admin-v2/orders-customers.css') }}">@endpush

@section('content')
@php $statusList = ['pending_approval','pending_payment','on_hold','paid','canceled','rejected','refunded','partially_refunded']; @endphp
<x-admin.page-header title="Orders" subtitle="Search, filter and manage every order." :crumbs="['Dashboard' => route('admin.dashboard'), 'Orders']">
    @can('orders.deleted.view')
        <a class="a-btn a-btn--outline" href="{{ route('admin.orders.deleted') }}"><i class="fa fa-trash-can"></i> Deleted Orders <x-admin.badge variant="dark">{{ $deletedOrdersCount ?? 0 }}</x-admin.badge></a>
    @endcan
    @if($canExport)
        <button type="button" class="a-btn a-btn--dark" data-bs-toggle="modal" data-bs-target="#exportOrdersModal"><i class="fa fa-download"></i> Export CSV</button>
    @endif
</x-admin.page-header>

<x-admin.flash />

<x-admin.filters :action="route('admin.orders.index')" :reset="route('admin.orders.index')">
    <div class="a-field"><label class="form-label" for="oSearch">Search</label>
        <input type="text" id="oSearch" name="search" value="{{ request('search') }}" class="form-control" placeholder="Order # / customer"></div>
    <div class="a-field"><label class="form-label" for="oStatus">Status</label>
        <select name="status" id="oStatus" class="form-select no-select2">
            <option value="">All</option>
            @foreach($statusList as $status)
                <option value="{{ $status }}" @selected(request('status') === $status)>{{ str($status)->headline() }}</option>
            @endforeach
        </select></div>
    <div class="a-field"><label class="form-label" for="oPay">Payment method</label>
        <select name="payment_method" id="oPay" class="form-select no-select2">
            <option value="">All</option>
            @foreach($paymentMethods as $method)
                <option value="{{ $method->code }}" @selected(request('payment_method') === $method->code)>{{ $method->checkout_label ?: $method->name }}</option>
            @endforeach
        </select></div>
    @if($canFilterByEvent)
    <div class="a-field"><label class="form-label" for="oEvent">Event</label>
        <select name="event_id" id="oEvent" class="form-select no-select2">
            <option value="">All Events</option>
            @foreach($events as $event)
                <option value="{{ $event->id }}" @selected(request('event_id') == $event->id)>{{ $event->name }}</option>
            @endforeach
        </select></div>
    @endif
</x-admin.filters>

<x-admin.card flush title="All Orders" :subtitle="$orders->total().' total'">
    @if($orders->isEmpty())
        <x-admin.empty icon="fa-receipt" title="No orders found" text="Try changing or resetting the filters." />
    @else
    <x-admin.table stack caption="Orders">
        <thead>
            <tr><th>Order</th><th>Customer</th><th class="a-num">Items</th><th>Event</th><th>Ticket types</th><th class="a-num">Total</th><th>Status</th><th>Payment deadline</th><th class="a-num">Actions</th></tr>
        </thead>
        <tbody>
        @foreach($orders as $order)
            @php
                $names = $order->items->pluck('ticket_name');
                $eventNames = $names->map(fn ($n) => str_contains((string) $n, ' - ') ? trim((string) str($n)->before(' - ')) : null)->filter()->unique();
                $ticketTypes = $names->map(fn ($n) => trim((string) \Illuminate\Support\Str::afterLast((string) $n, ' - ')))->unique();
                $secondsLeft = $order->paymentSecondsRemaining();
            @endphp
            <tr>
                <td data-label="Order"><a class="a-table__main" href="{{ route('admin.orders.show', $order) }}">{{ preg_replace('/\D+/', '', (string) $order->order_number) ?: $order->order_number }}</a>
                    <span class="a-table__sub">{{ $order->created_at?->format('d M Y, H:i') }}</span></td>
                <td data-label="Customer" class="a-wrap"><span class="a-table__main">{{ $order->customer?->full_name }}</span><span class="a-table__sub">{{ $order->customer?->email }}</span></td>
                <td data-label="Items" class="a-num">{{ $order->items_count }}</td>
                <td data-label="Event" class="a-wrap"><div class="a-ord-tags">@foreach($eventNames as $eventName)<span class="a-ord-tag a-ord-tag--event">{{ $eventName }}</span>@endforeach</div></td>
                <td data-label="Ticket types" class="a-wrap"><div class="a-ord-tags">
                    @foreach($ticketTypes as $ticketType)
                        @php $ticketColor = $ticketColorMap[\Illuminate\Support\Str::lower(trim($ticketType))] ?? '#6c757d'; @endphp
                        <span class="a-ord-tag" style="background-color: {{ $ticketColor }}">{{ $ticketType }}</span>
                    @endforeach</div></td>
                <td data-label="Total" class="a-num">{{ number_format($order->total_amount, 2) }} EGP</td>
                <td data-label="Status"><x-admin.status :status="$order->status" kind="order" />
                    @if($order->payment_method)<span class="a-table__sub">{{ ucwords(str_replace('_', ' ', $order->payment_method)) }}</span>@endif</td>
                <td data-label="Payment deadline">
                    @if($order->status === 'pending_payment' && $secondsLeft !== null)
                        <span class="badge bg-warning text-dark a-ord-timer js-admin-order-timer" data-seconds-left="{{ max(0, $secondsLeft) }}">{{ $secondsLeft > 0 ? 'Loading timer...' : 'Expired' }}</span>
                    @else
                        <span class="a-muted">—</span>
                    @endif
                </td>
                <td data-label="Actions"><div class="a-table__actions">
                    @can('orders.update')
                        @if($order->status === 'pending_approval')
                            <form method="POST" action="{{ route('admin.orders.approve', $order) }}" data-a-confirm="Approve this order and send the payment link?" data-a-confirm-title="Approve order" data-a-confirm-ok="Approve">@csrf
                                <button class="a-btn a-btn--success a-btn--sm" type="submit" title="Approve" aria-label="Approve order"><i class="fa fa-check"></i></button>
                            </form>
                            <form method="POST" action="{{ route('admin.orders.reject', $order) }}" data-a-confirm="Reject this order?" data-a-confirm-title="Reject order" data-a-confirm-ok="Reject">@csrf
                                <button class="a-btn a-btn--danger a-btn--sm" type="submit" title="Reject" aria-label="Reject order"><i class="fa fa-times"></i></button>
                            </form>
                        @endif
                    @endcan
                    <a class="a-btn a-btn--outline a-btn--sm" href="{{ route('admin.orders.show', $order) }}" title="View" aria-label="View order"><i class="fa fa-eye"></i></a>
                    @can('orders.update')
                        <a class="a-btn a-btn--outline a-btn--sm" href="{{ route('admin.orders.edit', $order) }}" title="Edit" aria-label="Edit order"><i class="fa fa-pen"></i></a>
                    @endcan
                    @can('orders.delete')
                        <form method="POST" action="{{ route('admin.orders.destroy', $order) }}" data-a-confirm="Delete this order? It can be restored from Deleted Orders." data-a-confirm-title="Delete order" data-a-confirm-ok="Delete">
                            @csrf
                            @method('DELETE')
                            <button class="a-btn a-btn--danger a-btn--sm" type="submit" title="Delete" aria-label="Delete order"><i class="fa fa-trash"></i></button>
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

<script>
document.addEventListener('DOMContentLoaded', () => {
    const timers = document.querySelectorAll('.js-admin-order-timer');
    if (!timers.length) return;

    const render = (el, seconds) => {
        if (seconds <= 0) {
            el.textContent = 'Expired';
            el.classList.remove('bg-warning', 'text-dark');
            el.classList.add('bg-danger');
            return;
        }

        const days = Math.floor(seconds / 86400);
        const hours = Math.floor((seconds % 86400) / 3600);
        const mins = Math.floor((seconds % 3600) / 60);
        const secs = seconds % 60;
        const dd = days > 0 ? `${days}d ` : '';
        el.textContent = `${dd}${String(hours).padStart(2, '0')}:${String(mins).padStart(2, '0')}:${String(secs).padStart(2, '0')}`;
    };

    timers.forEach((el) => {
        let remaining = parseInt(el.dataset.secondsLeft || '0', 10);
        render(el, remaining);
        setInterval(() => {
            remaining -= 1;
            render(el, remaining);
        }, 1000);
    });
});
</script>

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
                            <select name="status" class="form-select no-select2" id="exportStatusSelect">
                                <option value="">All Statuses</option>
                                @foreach(['pending_approval','pending_payment','on_hold','paid','canceled','rejected','refunded','partially_refunded'] as $s)
                                    <option value="{{ $s }}">{{ str($s)->headline() }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Payment Method</label>
                            <select name="payment_method" class="form-select no-select2" id="exportPaymentSelect">
                                <option value="">All Payment Methods</option>
                                @foreach($paymentMethods as $method)
                                    <option value="{{ $method->code }}">{{ $method->checkout_label ?: $method->name }}</option>
                                @endforeach
                            </select>
                        </div>
                        @if($canFilterByEvent)
                        <div class="col-md-6">
                            <label class="form-label">Event</label>
                            <select name="event_id" class="form-select no-select2" id="exportEventSelect">
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

// Init select2 inside export modal — global init skips these (no-select2 class)
// We init manually on shown.bs.modal so dropdownParent is set correctly
if (typeof jQuery !== 'undefined' && typeof jQuery.fn.select2 !== 'undefined') {
    jQuery('#exportOrdersModal').on('shown.bs.modal', function () {
        const $modal = jQuery('#exportOrdersModal');
        ['#exportStatusSelect', '#exportPaymentSelect', '#exportEventSelect'].forEach(function (id) {
            const $el = jQuery(id);
            if (!$el.length || $el.data('select2')) return;
            $el.select2({ dropdownParent: $modal, width: '100%' });
        });
    });
}
</script>
@endsection
