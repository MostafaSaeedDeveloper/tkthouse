@extends('admin.master')

@section('content')
<div class="content">
    <div class="d-md-flex justify-content-md-between align-items-md-center mb-3">
        <div>
            <h1 class="h3 mb-1">Order Details</h1>
            <p class="text-muted mb-0">Single order view inspired by <code>be_pages_ecom_order.html</code>.</p>
        </div>
        <div><a href="{{ route('admin.orders.index') }}" class="btn btn-alt-secondary">Back</a></div>
    </div>

    <div class="block block-rounded mb-3">
        <div class="block-header block-header-default"><h3 class="block-title">Customer</h3></div>
        <div class="block-content">
            <p class="mb-1"><strong>{{ $order->customer?->full_name }}</strong></p>
            <p class="mb-1">{{ $order->customer?->email }}</p>
            <p class="mb-1">{{ $order->customer?->phone }}</p>
            <p class="mb-0">{{ $order->customer?->address }}</p>
        </div>
    </div>

    <div class="block block-rounded">
        <div class="block-header block-header-default d-flex justify-content-between">
            <h3 class="block-title">Tickets</h3>
            <span class="badge bg-primary">{{ $order->order_number }}</span>
        </div>
        <div class="block-content p-0">
            <div class="table-responsive">
                <table class="table table-hover table-vcenter mb-0">
                    <thead>
                        <tr>
                            <th>Ticket</th>
                            <th>Holder</th>
                            <th>Quantity</th>
                            <th>Price</th>
                            <th>Line Total</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($order->items as $item)
                        <tr>
                            <td>{{ $item->ticket_name }}</td>
                            <td>
                                {{ $item->holder_name }}<br>
                                <span class="fs-sm text-muted">{{ $item->holder_email }} - {{ $item->holder_phone }}</span>
                                @if($item->holder_gender || $item->holder_social_profile)
                                    <br>
                                    <span class="fs-sm text-muted">
                                        @if($item->holder_gender)
                                            Gender: {{ ucfirst($item->holder_gender) }}
                                        @endif
                                        @if($item->holder_gender && $item->holder_social_profile)
                                            |
                                        @endif
                                        @if($item->holder_social_profile)
                                            Social: {{ $item->holder_social_profile }}
                                        @endif
                                    </span>
                                @endif
                            </td>
                            <td>{{ $item->quantity }}</td>
                            <td>{{ number_format($item->ticket_price, 2) }}</td>
                            <td>{{ number_format($item->line_total, 2) }}</td>
                        </tr>
                        @endforeach
                    </tbody>
                    <tfoot>
                        <tr>
                            <th colspan="4" class="text-end">Total</th>
                            <th>{{ number_format($order->total_amount, 2) }}</th>
                        </tr>
                    </tfoot>
                </table>
            </div>
        </div>
    </div>
</div>
@endsection
