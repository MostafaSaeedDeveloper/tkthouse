@extends('admin.master')

@section('content')
<div class="content">
    <div class="block block-rounded mb-3">
        <div class="block-header block-header-default"><h3 class="block-title">{{ $customer->full_name }}</h3></div>
        <div class="block-content">
            <p>{{ $customer->email }} | {{ $customer->phone }}</p>
        </div>
    </div>

    <div class="block block-rounded">
        <div class="block-header block-header-default"><h3 class="block-title">Orders</h3></div>
        <div class="block-content p-0">
            <div class="table-responsive">
                <table class="table table-hover table-vcenter mb-0">
                    <thead><tr><th>Order</th><th>Items</th><th>Total</th><th>Date</th><th class="text-end">Action</th></tr></thead>
                    <tbody>
                        @forelse($customer->orders as $order)
                        <tr>
                            <td>{{ $order->order_number }}</td>
                            <td>{{ $order->items_count }}</td>
                            <td>{{ number_format($order->total_amount, 2) }}</td>
                            <td>{{ $order->created_at->format('Y-m-d H:i') }}</td>
                            <td class="text-end"><a class="btn btn-sm btn-alt-primary" href="{{ route('admin.orders.show', $order) }}"><i class="fa fa-eye"></i></a></td>
                        </tr>
                        @empty
                        <tr><td colspan="5" class="text-center py-4 text-muted">No orders for this customer.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>
@endsection
