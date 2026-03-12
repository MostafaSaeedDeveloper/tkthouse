@extends('front.layout.master')

@section('content')

@include('front.account.partials._acc_styles')

<div class="acc-banner">
    <div class="container">
        <div class="acc-banner-inner">
            <div>
                <div class="acc-banner-label">Account</div>
                <h1 class="acc-banner-title">My <span>Tickets</span></h1>
                <p class="acc-banner-sub">View and download all your event tickets</p>
            </div>
        </div>
    </div>
</div>

<section class="acc-page">
    <div class="container">

        @include('front.account.partials.navigation')

        <div class="acc-kpis">
            <div class="acc-kpi">
                <div class="acc-kpi-label">Total Tickets</div>
                <div class="acc-kpi-value">{{ number_format($ticketStats['total_tickets']) }}</div>
            </div>
            <div class="acc-kpi">
                <div class="acc-kpi-label">Assigned Holders</div>
                <div class="acc-kpi-value">{{ number_format($ticketStats['assigned_holders']) }}</div>
            </div>
            <div class="acc-kpi">
                <div class="acc-kpi-label">Delivered Tickets</div>
                <div class="acc-kpi-value">{{ number_format($ticketStats['delivered']) }}</div>
            </div>
            <div class="acc-kpi">
                <div class="acc-kpi-label">Portfolio Value</div>
                <div class="acc-kpi-value">{{ number_format($ticketStats['total_value'], 2) }} <span>EGP</span></div>
            </div>
        </div>

        <div class="acc-card">
            <div class="acc-card-head">
                <div class="acc-card-title">Ticket Collection</div>
            </div>
            <div style="overflow-x:auto;">
                <table class="acc-table">
                    <thead>
                        <tr>
                            <th>Ticket #</th>
                            <th>Ticket</th>
                            <th>Holder</th>
                            <th>Order #</th>
                            <th>Price</th>
                            <th>Delivery</th>
                            <th></th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($tickets as $ticket)
                            <tr>
                                <td class="acc-mono">{{ $ticket->ticket_number }}</td>
                                <td style="color:#fff;font-weight:500;">{{ $ticket->ticket_name ?: 'General Admission' }}</td>
                                <td style="color:#fff;font-weight:500;">{{ $ticket->holder_name ?: '—' }}</td>
                                <td class="acc-mono" style="font-size:11px;">{{ $ticket->order?->order_number ?? '—' }}</td>
                                <td style="font-weight:600;color:#fff;">{{ number_format($ticket->ticket_price,2) }} <span style="color:var(--muted);font-size:11px;">EGP</span></td>
                                <td>
                                    @if($ticket->sent_at)
                                        <span class="acc-badge acc-badge-paid">Sent</span>
                                    @else
                                        <span class="acc-badge acc-badge-pending">Pending</span>
                                    @endif
                                </td>
                                <td style="text-align:right;">
                                    <a href="{{ route('front.tickets.show', $ticket) }}" class="acc-btn">
                                        <i class="fa fa-eye" style="font-size:10px;"></i> View
                                    </a>
                                </td>
                            </tr>
                        @empty
                            <tr><td colspan="7"><div class="acc-empty"><i class="fa fa-ticket"></i><span>No tickets yet.</span></div></td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
            @if($tickets->hasPages())
                <div class="acc-page-footer">{{ $tickets->links() }}</div>
            @endif
        </div>

    </div>
</section>

@endsection
