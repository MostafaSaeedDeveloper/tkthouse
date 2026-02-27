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

    public function edit(Order $order)
    {
        $order->load(['customer', 'items.ticket', 'user']);

        return view('admin.orders.edit', compact('order'));
    }

    public function update(Request $request, Order $order)
    {
        $validated = $request->validate([
            'status' => ['required', 'string', 'max:100'],
            'payment_method' => ['required', 'string', 'max:100'],
            'payment_status' => ['required', 'string', 'max:100'],
            'requires_approval' => ['nullable', 'boolean'],
            'items' => ['array'],
            'items.*.id' => ['required', 'integer'],
            'items.*.quantity' => ['required', 'integer', 'min:1'],
            'items.*.holder_name' => ['required', 'string', 'max:255'],
            'items.*.holder_email' => ['required', 'email', 'max:255'],
            'items.*.holder_phone' => ['nullable', 'string', 'max:255'],
        ]);

        $order->update([
            'status' => $validated['status'],
            'payment_method' => $validated['payment_method'],
            'payment_status' => $validated['payment_status'],
            'requires_approval' => (bool) ($validated['requires_approval'] ?? false),
            'approved_at' => $validated['status'] === 'approved_pending_payment' ? ($order->approved_at ?? now()) : null,
        ]);

        $total = 0;
        $itemsInput = collect($validated['items'] ?? [])->keyBy('id');

        $order->load('items');
        foreach ($order->items as $item) {
            $updated = $itemsInput->get($item->id);
            if (! $updated) {
                continue;
            }

            $lineTotal = ((float) $item->ticket_price) * (int) $updated['quantity'];
            $item->update([
                'quantity' => (int) $updated['quantity'],
                'line_total' => $lineTotal,
                'holder_name' => $updated['holder_name'],
                'holder_email' => $updated['holder_email'],
                'holder_phone' => $updated['holder_phone'] ?: null,
            ]);

            $total += $lineTotal;
        }

        $order->update(['total_amount' => $total]);

        return redirect()->route('admin.orders.show', $order)->with('success', 'Order updated successfully.');
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
