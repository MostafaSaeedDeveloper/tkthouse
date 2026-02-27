@extends('front.layout.master')

@section('content')
<div class="sub-banner"><div class="container"><h6>My Dashboard</h6></div></div>
<section class="py-5" style="background:#090909;color:#fff;">
    <div class="container">
        @include('front.account.partials.navigation')

        <div class="card bg-dark border-secondary">
            <div class="card-header border-secondary text-warning">My Tickets</div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-dark table-hover mb-0 align-middle">
                        <thead>
                        <tr>
                            <th>Ticket #</th>
                            <th>Holder</th>
                            <th>Order #</th>
                            <th>Price</th>
                            <th>Actions</th>
                        </tr>
                        </thead>
                        <tbody>
                        @forelse($tickets as $ticket)
                            <tr>
                                <td>{{ $ticket->ticket_number }}</td>
                                <td>{{ $ticket->holder_name }}</td>
                                <td>{{ $ticket->order?->order_number }}</td>
                                <td>{{ number_format($ticket->ticket_price, 2) }} EGP</td>
                                <td><a class="btn btn-sm btn-outline-warning" href="{{ route('front.tickets.show', $ticket) }}">View</a></td>
                            </tr>
                        @empty
                            <tr><td colspan="5" class="text-center text-muted py-4">No tickets yet.</td></tr>
                        @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
            <div class="card-footer border-secondary">{{ $tickets->links() }}</div>
        </div>
    </div>
</section>
@endsection
