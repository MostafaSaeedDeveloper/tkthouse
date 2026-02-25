@extends('front.layout.master')

@section('content')
    <style>
        @import url('https://fonts.googleapis.com/css2?family=Bebas+Neue&family=Barlow:wght@300;400;500;600;700&display=swap');

        .checkout-page {
            background: #090909;
            padding: 70px 0 90px;
            color: #fff;
        }

        .checkout-title {
            font-family: 'Bebas Neue', sans-serif;
            font-size: 54px;
            letter-spacing: 4px;
            margin-bottom: 8px;
        }

        .checkout-subtitle {
            font-family: 'Barlow', sans-serif;
            color: rgba(255, 255, 255, 0.65);
            margin-bottom: 40px;
            font-size: 16px;
        }

        .checkout-card {
            background: #121212;
            border: 1px solid rgba(255, 255, 255, 0.08);
            padding: 28px;
            margin-bottom: 20px;
        }

        .checkout-card h4 {
            font-family: 'Bebas Neue', sans-serif;
            font-size: 28px;
            letter-spacing: 2px;
            margin: 0 0 18px;
            color: #f4c430;
        }

        .checkout-label {
            font-family: 'Barlow', sans-serif;
            font-size: 12px;
            text-transform: uppercase;
            letter-spacing: 1.5px;
            color: rgba(255, 255, 255, 0.65);
            margin-bottom: 7px;
        }

        .checkout-input {
            width: 100%;
            background: #0d0d0d;
            border: 1px solid rgba(255, 255, 255, 0.12);
            height: 46px;
            padding: 0 14px;
            color: #fff;
            margin-bottom: 14px;
            font-family: 'Barlow', sans-serif;
        }

        .ticket-item {
            display: flex;
            justify-content: space-between;
            border-bottom: 1px dashed rgba(255, 255, 255, 0.15);
            padding: 12px 0;
            font-family: 'Barlow', sans-serif;
        }

        .ticket-item strong {
            display: block;
            color: #fff;
            font-size: 15px;
        }

        .ticket-item span {
            color: rgba(255, 255, 255, 0.6);
            font-size: 12px;
        }

        .checkout-total {
            display: flex;
            justify-content: space-between;
            margin-top: 16px;
            font-family: 'Bebas Neue', sans-serif;
            font-size: 28px;
            letter-spacing: 2px;
        }

        .checkout-total .amount {
            color: #f4c430;
        }

        .checkout-btn {
            width: 100%;
            margin-top: 24px;
            height: 50px;
            border: none;
            background: #f4c430;
            color: #000;
            font-family: 'Bebas Neue', sans-serif;
            letter-spacing: 2px;
            font-size: 18px;
        }

        .note-box {
            margin-top: 16px;
            background: rgba(244, 196, 48, 0.1);
            border: 1px solid rgba(244, 196, 48, 0.3);
            padding: 12px;
            font-family: 'Barlow', sans-serif;
            color: rgba(255, 255, 255, 0.75);
            font-size: 13px;
        }
    </style>

    <section class="checkout-page">
        <div class="container">
            <h1 class="checkout-title">CHECKOUT</h1>
            <p class="checkout-subtitle">Complete your booking for <strong>TKT House Techno Night</strong> (hardcoded demo data).</p>

            <div class="row">
                <div class="col-md-7">
                    <div class="checkout-card">
                        <h4>Billing Details</h4>
                        <div class="row">
                            <div class="col-sm-6">
                                <label class="checkout-label">First Name</label>
                                <input class="checkout-input" type="text" value="Ahmed">
                            </div>
                            <div class="col-sm-6">
                                <label class="checkout-label">Last Name</label>
                                <input class="checkout-input" type="text" value="Hassan">
                            </div>
                            <div class="col-sm-6">
                                <label class="checkout-label">Email</label>
                                <input class="checkout-input" type="text" value="ahmed.hassan@email.com">
                            </div>
                            <div class="col-sm-6">
                                <label class="checkout-label">Phone</label>
                                <input class="checkout-input" type="text" value="+20 101 234 5678">
                            </div>
                            <div class="col-sm-12">
                                <label class="checkout-label">Ticket Holder Name</label>
                                <input class="checkout-input" type="text" value="Ahmed Hassan">
                            </div>
                            <div class="col-sm-12">
                                <label class="checkout-label">Address</label>
                                <input class="checkout-input" type="text" value="90 Street, New Cairo, Egypt">
                            </div>
                        </div>
                    </div>

                    <div class="checkout-card">
                        <h4>Payment Method</h4>
                        <label class="checkout-label">Card Holder Name</label>
                        <input class="checkout-input" type="text" value="Ahmed Hassan">
                        <label class="checkout-label">Card Number</label>
                        <input class="checkout-input" type="text" value="4111 1111 1111 1111">

                        <div class="row">
                            <div class="col-sm-6">
                                <label class="checkout-label">Expiry Date</label>
                                <input class="checkout-input" type="text" value="12/27">
                            </div>
                            <div class="col-sm-6">
                                <label class="checkout-label">CVV</label>
                                <input class="checkout-input" type="text" value="123">
                            </div>
                        </div>
                    </div>
                </div>

                <div class="col-md-5">
                    <div class="checkout-card">
                        <h4>Order Summary</h4>

                        <div class="ticket-item">
                            <div>
                                <strong>Early Bird Ticket × 2</strong>
                                <span>Gate opens at 9:00 PM</span>
                            </div>
                            <strong>1,200 EGP</strong>
                        </div>

                        <div class="ticket-item">
                            <div>
                                <strong>VIP Backstage × 1</strong>
                                <span>Priority entry + lounge access</span>
                            </div>
                            <strong>1,500 EGP</strong>
                        </div>

                        <div class="ticket-item">
                            <div>
                                <strong>Service Fees</strong>
                                <span>Booking & processing</span>
                            </div>
                            <strong>120 EGP</strong>
                        </div>

                        <div class="checkout-total">
                            <span>Total</span>
                            <span class="amount">2,820 EGP</span>
                        </div>

                        <button class="checkout-btn" type="button">Place Order</button>

                        <div class="note-box">
                            This checkout page is a front-end demo only. All values are hardcoded and no real payment is processed.
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>
@endsection
