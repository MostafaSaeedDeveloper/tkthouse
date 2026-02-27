@extends('front.layout.master')

@section('content')
<div class="sub-banner"><div class="container"><h6>Order Payment</h6></div></div>
<section class="py-5" style="background:#090909;color:#fff;">
    <div class="container">
        <div class="row justify-content-center">
            <div class="col-lg-8">
                <div class="card bg-dark border-secondary">
                    <div class="card-body">
                        <h4 class="text-warning mb-3">Order {{ $order->order_number }}</h4>
                        <p>Status: <strong>{{ ucfirst(str_replace('_', ' ', $order->status)) }}</strong></p>
                        <p>Payment Method: <strong>{{ ucfirst($order->payment_method) }}</strong></p>
                        <ul>
                            @foreach($order->items as $item)
                                <li>{{ $item->ticket_name }} x{{ $item->quantity }} - {{ number_format($item->line_total, 2) }} EGP</li>
                            @endforeach
                        </ul>
                        <h5>Total: {{ number_format($order->total_amount, 2) }} EGP</h5>

                        <form method="POST" action="{{ route('front.orders.payment.confirm', ['order' => $order, 'token' => $order->payment_link_token]) }}">
                            @csrf
                            <button class="btn btn-warning w-100">Confirm Payment</button>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </div>
</section>
@endsection
