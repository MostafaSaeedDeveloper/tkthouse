@extends('front.layout.master')

@section('content')
<section class="about-page-section" style="padding:40px 0;">
    <div class="container">
        <div class="row justify-content-center">
            <div class="col-lg-7">
                <div class="card shadow-sm border-0">
                    <div class="card-body p-4">
                        <h4 class="mb-3">Ticket #{{ $ticket->ticket_number }}</h4>
                        <p class="mb-1"><strong>Name:</strong> {{ $ticket->holder_name }}</p>
                        <p class="mb-1"><strong>Email:</strong> {{ $ticket->holder_email }}</p>
                        <p class="mb-1"><strong>Ticket:</strong> {{ $ticket->ticket_name }}</p>
                        <p class="mb-3"><strong>Order:</strong> {{ $ticket->order->order_number }}</p>

                        <div class="text-center mb-3">
                            <img src="{{ $ticket->qrUrl() }}" alt="QR Code" style="max-width:220px; width:100%;">
                        </div>

                        <div class="d-flex gap-2 justify-content-center">
                            <a href="{{ route('front.tickets.download', $ticket) }}" class="btn btn-warning">Download PDF</a>
                            <a href="{{ route('front.account.profile') }}" class="btn btn-outline-secondary">Back to Dashboard</a>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</section>
@endsection
