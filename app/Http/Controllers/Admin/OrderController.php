<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Order;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;

class OrderController extends Controller
{
    public function index()
    {
        $orders = Order::withCount('items')->with('customer')->latest()->paginate(15);

        return view('admin.orders.index', compact('orders'));
    }

    public function show(Order $order)
    {
        $order->load(['customer', 'items.ticket', 'user']);

        return view('admin.orders.show', compact('order'));
    }

    public function approve(Request $request, Order $order)
    {
        if ($order->status !== 'pending_approval') {
            return back()->with('error', 'Only pending approval orders can be approved.');
        }

        $order->update([
            'status' => 'approved_pending_payment',
            'approved_at' => now(),
            'payment_link_token' => Str::random(40),
        ]);

        $paymentLink = route('front.orders.payment', ['order' => $order, 'token' => $order->payment_link_token]);

        Mail::raw(
            "Your order {$order->order_number} has been approved and is now waiting for payment.\n\nPay now: {$paymentLink}",
            static function ($message) use ($order) {
                $message->to($order->customer->email)
                    ->subject('Order approved - payment required');
            }
        );

        return back()->with('success', 'Order approved and payment email sent successfully.');
    }
}
