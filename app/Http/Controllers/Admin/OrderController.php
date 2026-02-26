<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Services\PaymentService;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

class OrderController extends Controller
{
    public function index(): View
    {
        return view('admin.orders.index', [
            'orders' => Order::with(['user', 'paymentMethod'])->latest()->paginate(20),
        ]);
    }

    public function show(Order $order): View
    {
        $order->loadMissing(['items.ticketType.event', 'payments', 'tickets']);

        return view('admin.orders.show', compact('order'));
    }

    public function markPaid(Order $order): RedirectResponse
    {
        app(PaymentService::class)->markAsPaid($order);

        return back()->with('status', 'Order marked as paid.');
    }
}
