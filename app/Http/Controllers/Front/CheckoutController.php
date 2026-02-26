<?php

namespace App\Http\Controllers\Front;

use App\Http\Controllers\Controller;
use App\Http\Requests\Front\CheckoutRequest;
use App\Models\Order;
use App\Models\TicketType;
use App\Services\FeeCalculator;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class CheckoutController extends Controller
{
    public function store(CheckoutRequest $request)
    {
        $data = $request->validated();

        $order = DB::transaction(function () use ($data) {
            $order = Order::create([
                'order_no' => 'ORD-'.strtoupper(Str::random(10)),
                'user_id' => auth()->id(),
                'customer_name' => $data['customer_name'] ?? auth()->user()?->name,
                'customer_phone' => $data['customer_phone'] ?? auth()->user()?->phone,
                'customer_email' => $data['customer_email'] ?? auth()->user()?->email,
                'payment_method_id' => $data['payment_method_id'] ?? null,
            ]);

            $subtotal = 0;
            foreach ($data['items'] as $item) {
                $type = TicketType::with('event')->findOrFail($item['ticket_type_id']);
                abort_unless($type->is_available, 422, 'ticket type unavailable');
                abort_if($type->sale_starts_at && now()->lt($type->sale_starts_at), 422, 'sale not started');
                abort_if($type->sale_ends_at && now()->gt($type->sale_ends_at), 422, 'sale ended');
                abort_if(($type->qty_total - $type->qty_sold) < $item['qty'], 422, 'insufficient inventory');

                $line = $type->price * $item['qty'];
                $subtotal += $line;
                $order->items()->create([
                    'ticket_type_id' => $type->id,
                    'qty' => $item['qty'],
                    'unit_price' => $type->price,
                    'line_subtotal' => $line,
                    'line_total' => $line,
                ]);
            }

            $order->update(['subtotal' => $subtotal]);
            $fees = app(FeeCalculator::class)->calculate($order->fresh('items.ticketType.event.feesPolicy.rules', 'paymentMethod.feesPolicy.rules'));
            foreach ($order->items as $orderItem) {
                $orderItem->update(['line_fees' => $fees['item_fees'][$orderItem->id] ?? 0, 'line_total' => $orderItem->line_subtotal + ($fees['item_fees'][$orderItem->id] ?? 0)]);
            }
            $order->update(['fees_total' => $fees['fees_total'], 'total' => $subtotal + $fees['fees_total']]);

            return $order;
        });

        return redirect()->route('front.orders.success', $order->order_no);
    }

    public function success(string $orderNo) { return view('front.orders.success', ['order' => Order::whereOrderNo($orderNo)->firstOrFail()]); }
}
