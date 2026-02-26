@extends('admin.master')

@section('content')
<div class="content">
    @include('admin.partials.flash')

    <div class="d-flex justify-content-between align-items-center mb-3">
        <h1 class="h3 mb-0">Order {{ $order->order_number }}</h1>
        <a href="{{ route('admin.orders.index') }}" class="btn btn-alt-secondary btn-sm">Back</a>
    </div>

    <div class="row g-3">
        <div class="col-lg-8">
            <div class="block block-rounded">
                <div class="block-header block-header-default"><h3 class="block-title">Order Items</h3></div>
                <div class="block-content p-0">
                    <table class="table table-vcenter mb-0">
                        <thead><tr><th>Ticket</th><th>Qty</th><th>Unit</th><th>Total</th></tr></thead>
                        <tbody>
                            @foreach($order->items as $item)
                                <tr>
                                    <td>{{ $item->ticket_name }}</td>
                                    <td>{{ $item->quantity }}</td>
                                    <td>{{ number_format($item->unit_price, 2) }}</td>
                                    <td>{{ number_format($item->line_total, 2) }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </div>

            <div class="block block-rounded">
                <div class="block-header block-header-default"><h3 class="block-title">Generated Tickets</h3></div>
                <div class="block-content p-0">
                    <table class="table table-vcenter mb-0">
                        <thead><tr><th>Code</th><th>Name</th><th>Status</th><th>Issued</th></tr></thead>
                        <tbody>
                            @foreach($order->tickets as $ticket)
                                <tr>
                                    <td>{{ $ticket->ticket_code }}</td>
                                    <td>{{ $ticket->ticket_name }}</td>
                                    <td>{{ ucfirst($ticket->status) }}</td>
                                    <td>{{ optional($ticket->issued_at)->format('Y-m-d H:i') }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
        <div class="col-lg-4">
            <div class="block block-rounded">
                <div class="block-content">
                    <p class="mb-1"><strong>Customer:</strong> {{ $order->customer?->full_name }}</p>
                    <p class="mb-1"><strong>Email:</strong> {{ $order->customer?->email }}</p>
                    <p class="mb-1"><strong>Event:</strong> {{ $order->event?->name }}</p>
                    <p class="mb-1"><strong>Sub Total:</strong> {{ number_format($order->sub_total, 2) }} EGP</p>
                    <p class="mb-1"><strong>Fees:</strong> {{ number_format($order->fees_total, 2) }} EGP</p>
                    <p class="mb-3"><strong>Grand Total:</strong> {{ number_format($order->grand_total, 2) }} EGP</p>

                    <form method="POST" action="{{ route('admin.orders.update', $order) }}">
                        @csrf
                        @method('PUT')
                        <div class="mb-3">
                            <label class="form-label">Status</label>
                            <select name="status" class="form-select">
                                @foreach(['pending','completed','cancelled'] as $status)
                                    <option value="{{ $status }}" @selected($order->status === $status)>{{ ucfirst($status) }}</option>
                                @endforeach
                            </select>
                        </div>
                        <button class="btn btn-primary w-100">Update Status</button>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection
