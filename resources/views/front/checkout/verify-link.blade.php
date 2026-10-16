@extends('front.layout.app')

@section('title', 'Confirm it\'s you')

@section('content')
<section class="tkt-container tkt-auth-page">
    <div class="tkt-auth-card">
        <div class="tkt-auth-card__body">
            <div class="tkt-auth__heading">
                <h1 class="tkt-h2">Confirm it's you</h1>
                <p>To protect your booking, enter the code we send to the email on this order (order {{ $order->order_number }}).</p>
            </div>
            <x-tkt.otp-panel :email="$email" :email-masked="$emailMasked" :order="$order->order_number" :token="$token" :after="request()->fullUrl()" :autosend="true" id-suffix="link" />
        </div>
    </div>
</section>
@endsection

@push('scripts')
    <script src="{{ asset('js/tkt-otp.js') }}?v={{ filemtime(public_path('js/tkt-otp.js')) }}"></script>
@endpush
