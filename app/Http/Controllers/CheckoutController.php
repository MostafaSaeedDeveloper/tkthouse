<?php

namespace App\Http\Controllers;

use App\Models\Customer;
use App\Models\Order;
use App\Models\Ticket;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class CheckoutController extends Controller
{
    public function index()
    {
        $tickets = Ticket::where('status', 'active')->orderBy('name')->get();

        return view('front.checkout', compact('tickets'));
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'first_name' => ['required', 'string', 'max:255'],
            'last_name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255'],
            'phone' => ['nullable', 'string', 'max:255'],
            'address' => ['nullable', 'string', 'max:255'],
            'items' => ['required', 'array'],
        ]);

        $items = collect($request->input('items', []))
            ->filter(fn ($item) => ! empty($item['ticket_id'] ?? null))
            ->values()
            ->all();

        if (empty($items)) {
            return back()->withErrors(['items' => 'Please add at least one ticket.'])->withInput();
        }

        $request->merge(['items' => $items]);

        $validated = $request->validate([
            'items.*.ticket_id' => ['required', 'exists:tickets,id'],
            'items.*.quantity' => ['required', 'integer', 'min:1'],
            'items.*.holder_name' => ['required', 'string', 'max:255'],
            'items.*.holder_email' => ['required', 'email', 'max:255'],
            'items.*.holder_phone' => ['nullable', 'string', 'max:255'],
        ]) + $validated;

        DB::transaction(function () use ($validated, $items) {
            $customer = Customer::firstOrCreate(
                ['email' => $validated['email']],
                [
                    'first_name' => $validated['first_name'],
                    'last_name' => $validated['last_name'],
                    'phone' => $validated['phone'] ?? null,
                    'address' => $validated['address'] ?? null,
                ]
            );

            $customer->update([
                'first_name' => $validated['first_name'],
                'last_name' => $validated['last_name'],
                'phone' => $validated['phone'] ?? null,
                'address' => $validated['address'] ?? null,
            ]);

            $order = Order::create([
                'customer_id' => $customer->id,
                'order_number' => 'ORD-'.now()->format('YmdHis').'-'.str_pad((string) random_int(1, 999), 3, '0', STR_PAD_LEFT),
                'status' => 'confirmed',
                'total_amount' => 0,
            ]);

            $ticketIds = collect($items)->pluck('ticket_id');
            $tickets = Ticket::whereIn('id', $ticketIds)->get()->keyBy('id');
            $total = 0;

            foreach ($items as $item) {
                $ticket = $tickets->get($item['ticket_id']);
                $lineTotal = $ticket->price * $item['quantity'];
                $total += $lineTotal;

                $order->items()->create([
                    'ticket_id' => $ticket->id,
                    'ticket_name' => $ticket->name,
                    'ticket_price' => $ticket->price,
                    'quantity' => $item['quantity'],
                    'line_total' => $lineTotal,
                    'holder_name' => $item['holder_name'],
                    'holder_email' => $item['holder_email'],
                    'holder_phone' => $item['holder_phone'] ?? null,
                ]);
            }

            $order->update(['total_amount' => $total]);
        });

        return redirect()->route('front.checkout')->with('success', 'Order completed successfully.');
    }
}
