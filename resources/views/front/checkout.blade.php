@extends('front.layout.master')

@section('content')
<div class="sub-banner">
    <div class="container">
        <h6>Checkout</h6>
    </div>
</div>

<section class="checkout-page py-5" style="background:#090909;color:#fff;">
    <div class="container">
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

        <form method="POST" action="{{ route('front.checkout.store') }}">
            @csrf
            <div class="row">
                <div class="col-md-5">
                    <h4 class="mb-3 text-warning">Customer Info</h4>
                    <div class="mb-2"><input class="form-control" name="first_name" placeholder="First name" value="{{ old('first_name') }}" required></div>
                    <div class="mb-2"><input class="form-control" name="last_name" placeholder="Last name" value="{{ old('last_name') }}" required></div>
                    <div class="mb-2"><input class="form-control" type="email" name="email" placeholder="Email" value="{{ old('email') }}" required></div>
                    <div class="mb-2"><input class="form-control" name="phone" placeholder="Phone" value="{{ old('phone') }}"></div>
                    <div class="mb-2"><input class="form-control" name="address" placeholder="Address" value="{{ old('address') }}"></div>
                </div>

                <div class="col-md-7">
                    <h4 class="mb-3 text-warning">Tickets</h4>
                    @for($i = 0; $i < 3; $i++)
                        <div class="card bg-dark border-secondary mb-3">
                            <div class="card-body">
                                <div class="row g-2">
                                    <div class="col-md-4">
                                        <label class="form-label">Ticket</label>
                                        <select name="items[{{ $i }}][ticket_id]" class="form-select">
                                            <option value="">Select ticket</option>
                                            @foreach($tickets as $ticket)
                                                <option value="{{ $ticket->id }}" @selected(old("items.$i.ticket_id") == $ticket->id)>{{ $ticket->name }} - {{ number_format($ticket->price,2) }}</option>
                                            @endforeach
                                        </select>
                                    </div>
                                    <div class="col-md-2">
                                        <label class="form-label">Qty</label>
                                        <input type="number" min="1" class="form-control" name="items[{{ $i }}][quantity]" value="{{ old("items.$i.quantity", 1) }}">
                                    </div>
                                    <div class="col-md-6">
                                        <label class="form-label">Holder Name</label>
                                        <input type="text" class="form-control" name="items[{{ $i }}][holder_name]" value="{{ old("items.$i.holder_name") }}">
                                    </div>
                                    <div class="col-md-6">
                                        <label class="form-label">Holder Email</label>
                                        <input type="email" class="form-control" name="items[{{ $i }}][holder_email]" value="{{ old("items.$i.holder_email") }}">
                                    </div>
                                    <div class="col-md-6">
                                        <label class="form-label">Holder Phone</label>
                                        <input type="text" class="form-control" name="items[{{ $i }}][holder_phone]" value="{{ old("items.$i.holder_phone") }}">
                                    </div>
                                </div>
                            </div>
                        </div>
                    @endfor

                    <button type="submit" class="btn btn-warning w-100">Place Order</button>
                </div>
            </div>
        </form>
    </div>
</section>
@endsection
