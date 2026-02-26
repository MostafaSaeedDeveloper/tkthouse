@extends('front.layout.master')

@section('content')
    <div class="sub-banner">
        <div class="container">
            <h6>Checkout</h6>
        </div>
    </div>

    <section class="py-5" style="background:#090909;color:#fff;min-height:60vh;">
        <div class="container">
            @if(!$event)
                <div class="alert alert-warning">Please choose an event first.</div>
            @else
                @if(session('success'))
                    <div class="alert alert-success">{{ session('success') }}</div>
                @endif
                @if($errors->any())
                    <div class="alert alert-danger">
                        <ul class="mb-0">
                            @foreach($errors->all() as $error)
                                <li>{{ $error }}</li>
                            @endforeach
                        </ul>
                    </div>
                @endif

                <div class="row g-4">
                    <div class="col-md-7">
                        <form method="POST" action="{{ route('front.checkout.store') }}" class="p-4" style="background:#121212;border:1px solid rgba(255,255,255,.08);">
                            @csrf
                            <input type="hidden" name="event_id" value="{{ $event->id }}">

                            <h4 class="mb-3">Billing Details</h4>
                            <div class="row g-3 mb-3">
                                <div class="col-sm-6"><input class="form-control" name="first_name" placeholder="First Name" value="{{ old('first_name') }}" required></div>
                                <div class="col-sm-6"><input class="form-control" name="last_name" placeholder="Last Name" value="{{ old('last_name') }}" required></div>
                                <div class="col-sm-6"><input type="email" class="form-control" name="email" placeholder="Email" value="{{ old('email') }}" required></div>
                                <div class="col-sm-6"><input class="form-control" name="phone" placeholder="Phone" value="{{ old('phone') }}"></div>
                                <div class="col-12"><input class="form-control" name="address" placeholder="Address" value="{{ old('address') }}"></div>
                            </div>

                            <h4 class="mb-3">Select Tickets</h4>
                            @foreach($event->tickets as $ticket)
                                <div class="d-flex justify-content-between align-items-center border-bottom py-2">
                                    <div>
                                        <div class="fw-bold">{{ $ticket->name }}</div>
                                        <small>{{ number_format($ticket->price, 2) }} EGP</small>
                                    </div>
                                    <input type="number" min="0" class="form-control" style="width:100px" name="tickets[{{ $ticket->id }}]" value="{{ old('tickets.'.$ticket->id, 0) }}">
                                </div>
                            @endforeach

                            <button class="btn btn-warning w-100 mt-4" type="submit">Place Order</button>
                        </form>
                    </div>

                    <div class="col-md-5">
                        <div class="p-4" style="background:#121212;border:1px solid rgba(255,255,255,.08);">
                            <h4 class="mb-2">{{ $event->name }}</h4>
                            <p class="text-light-emphasis mb-2">{{ $event->event_date?->format('Y-m-d') }} - {{ $event->location }}</p>
                            <hr>
                            <h5>Event Fees</h5>
                            @forelse($event->fees as $fee)
                                <div class="d-flex justify-content-between">
                                    <span>{{ $fee->name }}</span>
                                    <span>{{ $fee->fee_type === 'percentage' ? $fee->value.'%' : number_format($fee->value,2).' EGP' }}</span>
                                </div>
                            @empty
                                <small class="text-light-emphasis">No extra fees.</small>
                            @endforelse
                        </div>
                    </div>
                </div>
            @endif
        </div>
    </section>
@endsection
