@extends('admin.master')

@section('content')
<div class="content">
    @include('admin.partials.flash')

    <div class="d-md-flex justify-content-md-between align-items-md-center mb-3">
        <div>
            <h1 class="h3 mb-1">Orders</h1>
            <p class="text-muted mb-0">Orders list.</p>
        </div>
    </div>

    <div class="block block-rounded mb-3">
        <div class="block-content py-3">
            <form method="GET" class="row g-2 align-items-end">
                <div class="col-md-3">
                    <label class="form-label">Search</label>
                    <input type="text" name="search" value="{{ request('search') }}" class="form-control" placeholder="Order # / customer">
                </div>
                <div class="col-md-2">
                    <label class="form-label">Status</label>
                    <select name="status" class="form-select js-select2">
                        <option value="">All</option>
                        @foreach(['pending_approval','pending_payment','on_hold','paid','canceled','rejected','refunded','partially_refunded'] as $status)
                            <option value="{{ $status }}" @selected(request('status') === $status)>{{ str($status)->headline() }}</option>
                        @endforeach
                    </select>
                </div>                <div class="col-md-2">
                    <label class="form-label">Payment Method</label>
                    <select name="payment_method" class="form-select js-select2">
                        <option value="">All</option>
                        @foreach($paymentMethods as $method)
                            <option value="{{ $method->code }}" @selected(request('payment_method') === $method->code)>{{ $method->checkout_label ?: $method->name }}</option>
                        @endforeach
                    </select>
                </div>
                @if($canFilterByEvent)
                <div class="col-md-3">
                    <label class="form-label">Event</label>
                    <select name="event_id" class="form-select js-select2">
                        <option value="">All Events</option>
                        @foreach($events as $event)
                            <option value="{{ $event->id }}" @selected(request('event_id') == $event->id)>{{ $event->name }}</option>
                        @endforeach
                    </select>
                </div>
                @endif
                <div class="col-md-3 d-flex gap-2">
                    <button class="btn btn-primary" type="submit">Filter</button>
                    <a class="btn btn-alt-secondary" href="{{ route('admin.orders.index') }}">Reset</a>
                </div>
            </form>
        </div>
    </div>

    <div class="block block-rounded">
        <div class="block-header block-header-default">
            <h3 class="block-title">All Orders</h3>
            <div class="block-options d-flex align-items-center gap-2">
                @can('orders.deleted.view')
                    <a class="btn btn-sm btn-alt-warning" href="{{ route('admin.orders.deleted') }}">
                        Deleted Orders <span class="badge bg-dark ms-1">{{ $deletedOrdersCount ?? 0 }}</span>
                    </a>
                @endcan
                @can('orders.view')
                    <button type="button" class="btn btn-sm btn-alt-success" data-bs-toggle="modal" data-bs-target="#exportOrdersModal">
                        <i class="fa fa-download me-1"></i> Export CSV
                    </button>
                @endcan
                <span class="badge bg-primary">{{ $orders->total() }} Total</span>
            </div>
        </div>
        <div class="block-content p-0">
            <div class="table-responsive">
                <table class="table table-hover table-vcenter mb-0">
                    <thead>
                        <tr>
                            <th>Order #</th><th>Customer</th><th>Items</th><th>Event</th><th>Ticket Types</th><th>Total</th><th>Status</th><th>Payment Deadline</th><th class="text-end">Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($orders as $order)
                        <tr>
                            <td class="fw-semibold">
                                <a href="{{ route('admin.orders.show', $order) }}">
                                    {{ preg_replace('/\D+/', '', (string) $order->order_number) ?: $order->order_number }}
                                </a>
                            </td>
                            <td>{{ $order->customer?->full_name }}<br><span class="fs-sm text-muted">{{ $order->customer?->email }}</span></td>
                            <td>{{ $order->items_count }}</td>
                            <td style="max-width: 320px;">
                                <div class="d-flex flex-wrap gap-1">
                                    @foreach($order->items->pluck('ticket_name')->map(fn ($ticketName) => str_contains((string) $ticketName, ' - ') ? trim((string) str($ticketName)->before(' - ')) : null)->filter()->unique() as $eventName)
                                        <span class="badge bg-secondary" style="display: inline-block; white-space: normal; line-height: 1.2; max-width: 280px; word-break: break-word;">{{ $eventName }}</span>
                                    @endforeach
                                </div>
                            </td>
                            <td style="max-width: 260px;">
                                <div class="d-flex flex-wrap gap-1">
                                    @foreach($order->items->pluck('ticket_name')->map(fn ($ticketName) => trim((string) 
                                        \Illuminate\Support\Str::afterLast((string) $ticketName, ' - ')))->unique() as $ticketType)
                                        @php
                                            $normalizedType = \Illuminate\Support\Str::lower(trim((string) \Illuminate\Support\Str::afterLast($ticketType, ' - ')));
                                            $ticketColor = $ticketColorMap[$normalizedType] ?? '#6c757d';
                                        @endphp
                                        <span class="badge" style="background-color: {{ $ticketColor }}; color: #fff; display: inline-block; white-space: normal; line-height: 1.2; max-width: 220px; word-break: break-word;">{{ $ticketType }}</span>
                                    @endforeach
                                </div>
                            </td>
                            <td>{{ number_format($order->total_amount, 2) }} EGP</td>
                            @php
                                    $statusClass = match($order->status) {
                                        'paid' => 'bg-success',
                                        'pending_approval', 'pending_payment', 'on_hold' => 'bg-warning text-dark',
                                        'refunded', 'partially_refunded' => 'bg-primary',
                                        default => 'bg-danger',
                                    };
                                @endphp
                                <td><span class="badge {{ $statusClass }}">{{ ucwords(str_replace('_', ' ', $order->status)) }}</span></td>
                            <td>
                                @php $secondsLeft = $order->paymentSecondsRemaining(); @endphp
                                @if($order->status === 'pending_payment' && $secondsLeft !== null)
                                    <span class="badge bg-warning text-dark js-admin-order-timer" data-seconds-left="{{ max(0, $secondsLeft) }}">
                                        {{ $secondsLeft > 0 ? 'Loading timer...' : 'Expired' }}
                                    </span>
                                @else
                                    <span class="text-muted">—</span>
                                @endif
                            </td>
                            <td class="text-end" style="white-space: nowrap; min-width: 170px;">
                                <div class="d-inline-flex flex-nowrap align-items-center gap-1">
                                @can('orders.update')
                                    @if($order->status === 'pending_approval')
                                        <form class="d-inline" method="POST" action="{{ route('admin.orders.approve', $order) }}">@csrf
                                            <button class="btn btn-sm btn-alt-success" type="submit" title="Approve"><i class="fa fa-check"></i></button>
                                        </form>
                                        <form class="d-inline" method="POST" action="{{ route('admin.orders.reject', $order) }}">@csrf
                                            <button class="btn btn-sm btn-alt-danger" type="submit" title="Reject"><i class="fa fa-times"></i></button>
                                        </form>
                                    @endif
                                @endcan
                                <a class="btn btn-sm btn-alt-primary" href="{{ route('admin.orders.show', $order) }}"><i class="fa fa-eye"></i></a>
                                @can('orders.update')
                                    <a class="btn btn-sm btn-alt-warning" href="{{ route('admin.orders.edit', $order) }}"><i class="fa fa-pen"></i></a>
                                @endcan
                                @can('orders.delete')
                                    <form class="d-inline" method="POST" action="{{ route('admin.orders.destroy', $order) }}">
                                        @csrf
                                        @method('DELETE')
                                        <button class="btn btn-sm btn-alt-danger" type="submit" title="Delete"><i class="fa fa-trash"></i></button>
                                    </form>
                                @endcan
                                </div>
                            </td>
                        </tr>
                        @empty
                        <tr><td colspan="9" class="text-center py-4 text-muted">No orders found.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <div class="mt-3">{{ $orders->links() }}</div>
</div>

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
                    <div class="row g-2">
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
                        <div class="col-6 col-md-4">
                            <div class="form-check">
                                <input class="form-check-input export-col-check" type="checkbox" name="columns[]" value="{{ $colKey }}" id="col_{{ $colKey }}" checked>
                                <label class="form-check-label" for="col_{{ $colKey }}">{{ $colLabel }}</label>
                            </div>
                        </div>
                        @endforeach
                    </div>
                    <div class="mt-2 d-flex gap-2">
                        <button type="button" class="btn btn-sm btn-alt-secondary" id="selectAllCols">Select All</button>
                        <button type="button" class="btn btn-sm btn-alt-secondary" id="deselectAllCols">Deselect All</button>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-alt-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-success"><i class="fa fa-download me-1"></i> Download CSV</button>
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

// Init select2 inside export modal with dropdownParent so dropdowns appear correctly
if (typeof jQuery !== 'undefined' && typeof jQuery.fn.select2 !== 'undefined') {
    jQuery('#exportOrdersModal').on('shown.bs.modal', function () {
        const $modal = jQuery(this);
        $modal.find('select.no-select2').each(function () {
            if (!jQuery(this).data('select2')) {
                jQuery(this).select2({ dropdownParent: $modal, width: '100%' });
            }
        });
    });
}
</script>
@endsection
