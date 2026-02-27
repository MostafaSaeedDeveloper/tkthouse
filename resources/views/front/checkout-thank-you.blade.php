@extends('front.layout.master')

@section('content')
<div class="sub-banner"><div class="container"><h6>Thank You</h6></div></div>
<section class="py-5" style="background:#090909;color:#fff;">
    <div class="container">
        @if(session('success'))
            <div class="alert alert-success">{{ session('success') }}</div>
        @endif
        <div class="card bg-dark border-secondary">
            <div class="card-body text-center py-5">
                <h3 class="text-warning mb-3">Thank you for your order</h3>
                <p class="mb-0">Your order was submitted successfully. You can wait for approval or payment updates by email.</p>
            </div>
        </div>
    </div>
</section>
@endsection
