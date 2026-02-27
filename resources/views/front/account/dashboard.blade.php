@extends('front.layout.master')

@section('content')
<div class="sub-banner">
    <div class="container">
        <h6>My Account</h6>
    </div>
</div>

<section class="py-5" style="background:#090909;color:#fff;">
    <div class="container">
        @include('front.account.partials.navigation')

        <div class="card bg-dark border-secondary">
            <div class="card-body text-center py-5">
                <h4 class="text-warning mb-3">Account Home Moved</h4>
                <p class="text-muted mb-4">Please use Profile, Orders, or Tickets from the account navigation.</p>
                <a href="{{ route('front.account.profile') }}" class="btn btn-warning">Go to Profile</a>
            </div>
        </div>
    </div>
</section>
@endsection
