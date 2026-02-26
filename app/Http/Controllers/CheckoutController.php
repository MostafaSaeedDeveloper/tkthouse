<?php

namespace App\Http\Controllers;

use App\Models\Customer;
use App\Models\Event;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Ticket;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class CheckoutController extends Controller
{
    public function create(Request $request)
    {
        $eventId = $request->integer('event_id');

        $event = Event::with(['tickets' => function ($query) {
            $query->where('status', 'active');
        }, 'fees'])
            ->when($eventId, fn ($query) => $query->whereKey($eventId))
            ->first();

        return view('front.checkout', compact('event'));
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'event_id' => ['required', 'exists:events,id'],
            'first_name' => ['required', 'string', 'max:255'],
            'last_name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255'],
            'phone' => ['nullable', 'string', 'max:255'],
            'address' => ['nullable', 'string', 'max:500'],
            'tickets' => ['required', 'array'],
            'tickets.*' => ['nullable', 'integer', 'min:0'],
        ]);

        $event = Event::with('tickets', 'fees')->findOrFail($validated['event_id']);
        $selectedTickets = collect($validated['tickets'])
            ->filter(fn ($quantity) => (int) $quantity > 0)
            ->map(fn ($quantity) => (int) $quantity);

        if ($selectedTickets->isEmpty()) {
            return back()->withErrors(['tickets' => 'Please select at least one ticket.'])->withInput();
        }

        $ticketModels = $event->tickets->whereIn('id', $selectedTickets->keys());
        if ($ticketModels->count() !== $selectedTickets->count()) {
            return back()->withErrors(['tickets' => 'Selected tickets are invalid.'])->withInput();
        }

        $subTotal = $ticketModels->sum(function ($ticket) use ($selectedTickets) {
            return (float) $ticket->price * $selectedTickets[$ticket->id];
        });

        $feesTotal = $event->fees->sum(function ($fee) use ($subTotal) {
            if ($fee->fee_type === 'percentage') {
                return $subTotal * ((float) $fee->value / 100);
            }

            return (float) $fee->value;
        });

        $grandTotal = $subTotal + $feesTotal;

        DB::transaction(function () use ($validated, $event, $selectedTickets, $ticketModels, $subTotal, $feesTotal, $grandTotal) {
            $customer = Customer::updateOrCreate(
                ['email' => $validated['email']],
                [
                    'first_name' => $validated['first_name'],
                    'last_name' => $validated['last_name'],
                    'phone' => $validated['phone'] ?? null,
                    'address' => $validated['address'] ?? null,
                ]
            );

            $order = Order::create([
                'order_number' => 'ORD-' . now()->format('YmdHis') . '-' . Str::upper(Str::random(4)),
                'customer_id' => $customer->id,
                'event_id' => $event->id,
                'sub_total' => $subTotal,
                'fees_total' => $feesTotal,
                'grand_total' => $grandTotal,
                'status' => 'completed',
            ]);

            foreach ($ticketModels as $ticketModel) {
                $quantity = $selectedTickets[$ticketModel->id];
                $lineTotal = $quantity * (float) $ticketModel->price;

                $orderItem = OrderItem::create([
                    'order_id' => $order->id,
                    'event_ticket_id' => $ticketModel->id,
                    'ticket_name' => $ticketModel->name,
                    'unit_price' => $ticketModel->price,
                    'quantity' => $quantity,
                    'line_total' => $lineTotal,
                ]);

                for ($i = 0; $i < $quantity; $i++) {
                    Ticket::create([
                        'ticket_code' => 'TKT-' . Str::upper(Str::random(10)),
                        'order_id' => $order->id,
                        'order_item_id' => $orderItem->id,
                        'customer_id' => $customer->id,
                        'event_id' => $event->id,
                        'event_ticket_id' => $ticketModel->id,
                        'ticket_name' => $ticketModel->name,
                        'price' => $ticketModel->price,
                        'status' => 'active',
                        'issued_at' => now(),
                    ]);
                }
            }
        });

        return redirect()->route('front.checkout', ['event_id' => $event->id])->with('success', 'Order completed successfully.');
    }
}
