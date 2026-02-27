@extends('front.layout.master')

@section('content')
<div class="sub-banner">
    <div class="container">
        <h6>My Dashboard</h6>
    </div>
</div>

<section class="py-5" style="background:#090909;color:#fff;">
    <div class="container">
        @include('front.account.partials.tabs')

        <div class="row g-4 mb-4">
            <div class="col-md-6">
                <div class="card bg-dark border-secondary h-100">
                    <div class="card-body">
                        <h5 class="text-warning">Total Orders</h5>
                        <h2>{{ $ordersCount }}</h2>
                        <a href="{{ route('front.account.orders') }}" class="btn btn-sm btn-outline-warning">View all orders</a>
                    </div>
                </div>
            </div>
            <div class="col-md-6">
                <div class="card bg-dark border-secondary h-100">
                    <div class="card-body">
                        <h5 class="text-warning">Total Tickets</h5>
                        <h2>{{ $ticketsCount }}</h2>
                        <a href="{{ route('front.account.tickets') }}" class="btn btn-sm btn-outline-warning">View all tickets</a>
                    </div>
                </div>
            </div>
        </div>

        <div class="row g-4">
            <div class="col-lg-6">
                <div class="card bg-dark border-secondary h-100">
                    <div class="card-header text-warning border-secondary">Latest Orders</div>
                    <div class="card-body p-0">
                        <div class="table-responsive">
                            <table class="table table-dark table-hover mb-0">
                                <thead>
                                <tr>
                                    <th>Order #</th>
                                    <th>Status</th>
                                    <th>Total</th>
                                </tr>
                                </thead>
                                <tbody>
                                @forelse($latestOrders as $order)
                                    <tr>
                                        <td>{{ $order->order_number }}</td>
                                        <td>{{ ucwords(str_replace('_', ' ', $order->status)) }}</td>
                                        <td>{{ number_format($order->total_amount, 2) }} EGP</td>
                                    </tr>
                                @empty
                                    <tr><td colspan="3" class="text-center text-muted py-3">No orders yet.</td></tr>
                                @endforelse
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-lg-6">
                <div class="card bg-dark border-secondary h-100">
                    <div class="card-header text-warning border-secondary">Latest Tickets</div>
                    <div class="card-body p-0">
                        <div class="table-responsive">
                            <table class="table table-dark table-hover mb-0">
                                <thead>
                                <tr>
                                    <th>Ticket #</th>
                                    <th>Holder</th>
                                    <th>Action</th>
                                </tr>
                                </thead>
                                <tbody>
                                @forelse($latestTickets as $ticket)
                                    <tr>
                                        <td>{{ $ticket->ticket_number }}</td>
                                        <td>{{ $ticket->holder_name }}</td>
                                        <td><a href="{{ route('front.tickets.show', $ticket) }}" class="btn btn-sm btn-outline-warning">View</a></td>
                                    </tr>
                                @empty
                                    <tr><td colspan="3" class="text-center text-muted py-3">No tickets yet.</td></tr>
                                @endforelse
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</section>
@endsection
