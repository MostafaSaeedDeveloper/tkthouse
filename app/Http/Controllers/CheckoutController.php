<?php

namespace App\Http\Controllers;

use App\Models\Customer;
use App\Models\EventTicket;
use App\Models\Order;
use App\Models\Ticket;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class CheckoutController extends Controller
{
    public function index()
    {
        $eventTickets = EventTicket::query()
            ->with('event:id,name,status,event_date')
            ->where('status', 'active')
            ->get()
            ->filter(fn ($ticket) => $ticket->event && $ticket->event->status === 'active')
            ->sortBy(fn ($ticket) => [$ticket->event->event_date?->format('Y-m-d') ?? '9999-12-31', $ticket->name])
            ->values();

        $legacyTickets = Ticket::query()
            ->where('status', 'active')
            ->orderBy('name')
            ->get();

        return view('front.checkout', compact('eventTickets', 'legacyTickets'));
    }

    public function store(Request $request)
    {
        $baseValidated = $request->validate([
            'first_name' => ['required', 'string', 'max:255'],
            'last_name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255'],
            'phone' => ['nullable', 'string', 'max:255'],
            'address' => ['nullable', 'string', 'max:255'],
            'items' => ['required', 'array'],
        ]);

        $items = collect($request->input('items', []))
            ->map(function ($item) {
                return [
                    'ticket_key' => trim((string) ($item['ticket_key'] ?? '')),
                    'quantity' => (int) ($item['quantity'] ?? 1),
                    'holder_name' => trim((string) ($item['holder_name'] ?? '')),
                    'holder_email' => trim((string) ($item['holder_email'] ?? '')),
                    'holder_phone' => trim((string) ($item['holder_phone'] ?? '')),
                ];
            })
            ->filter(fn ($item) => $item['ticket_key'] !== '')
            ->values();

        if ($items->isEmpty()) {
            throw ValidationException::withMessages([
                'items' => 'Please select at least one ticket row.',
            ]);
        }

        $ticketErrors = [];
        foreach ($items as $index => $item) {
            if (! preg_match('/^(event|legacy):(\d+)$/', $item['ticket_key'], $matches)) {
                $ticketErrors["items.$index.ticket_key"] = 'Invalid ticket selection.';
            }

            if ($item['quantity'] < 1) {
                $ticketErrors["items.$index.quantity"] = 'Quantity must be at least 1.';
            }

            if ($item['holder_name'] === '') {
                $ticketErrors["items.$index.holder_name"] = 'Holder name is required for each selected ticket.';
            }

            if ($item['holder_email'] === '' || ! filter_var($item['holder_email'], FILTER_VALIDATE_EMAIL)) {
                $ticketErrors["items.$index.holder_email"] = 'A valid holder email is required for each selected ticket.';
            }
        }

        if (! empty($ticketErrors)) {
            throw ValidationException::withMessages($ticketErrors);
        }

        $grouped = $items->map(function ($item) {
            preg_match('/^(event|legacy):(\d+)$/', $item['ticket_key'], $matches);

            return $item + [
                'ticket_type' => $matches[1],
                'ticket_ref_id' => (int) $matches[2],
            ];
        });

        $eventTicketIds = $grouped->where('ticket_type', 'event')->pluck('ticket_ref_id')->all();
        $legacyTicketIds = $grouped->where('ticket_type', 'legacy')->pluck('ticket_ref_id')->all();

        $eventTickets = EventTicket::query()
            ->with('event:id,name,status')
            ->whereIn('id', $eventTicketIds)
            ->where('status', 'active')
            ->get()
            ->keyBy('id');

        $legacyTickets = Ticket::query()
            ->whereIn('id', $legacyTicketIds)
            ->where('status', 'active')
            ->get()
            ->keyBy('id');

        DB::transaction(function () use ($baseValidated, $grouped, $eventTickets, $legacyTickets) {
            $customer = Customer::updateOrCreate(
                ['email' => $baseValidated['email']],
                [
                    'first_name' => $baseValidated['first_name'],
                    'last_name' => $baseValidated['last_name'],
                    'phone' => $baseValidated['phone'] ?? null,
                    'address' => $baseValidated['address'] ?? null,
                ]
            );

            $order = Order::create([
                'customer_id' => $customer->id,
                'order_number' => 'ORD-'.now()->format('YmdHis').'-'.str_pad((string) random_int(1, 999), 3, '0', STR_PAD_LEFT),
                'status' => 'confirmed',
                'total_amount' => 0,
            ]);

            $total = 0;

            foreach ($grouped as $item) {
                if ($item['ticket_type'] === 'event') {
                    $ticket = $eventTickets->get($item['ticket_ref_id']);

                    if (! $ticket || ! $ticket->event || $ticket->event->status !== 'active') {
                        throw ValidationException::withMessages(['items' => 'Selected event ticket is no longer available.']);
                    }

                    $ticketName = ($ticket->event->name ? $ticket->event->name.' - ' : '').$ticket->name;
                    $ticketPrice = $ticket->price;
                    $orderTicketId = null;
                } else {
                    $ticket = $legacyTickets->get($item['ticket_ref_id']);

                    if (! $ticket) {
                        throw ValidationException::withMessages(['items' => 'Selected ticket is no longer available.']);
                    }

                    $ticketName = $ticket->name;
                    $ticketPrice = $ticket->price;
                    $orderTicketId = $ticket->id;
                }

                $lineTotal = $ticketPrice * $item['quantity'];
                $total += $lineTotal;

                $order->items()->create([
                    'ticket_id' => $orderTicketId,
                    'ticket_name' => $ticketName,
                    'ticket_price' => $ticketPrice,
                    'quantity' => $item['quantity'],
                    'line_total' => $lineTotal,
                    'holder_name' => $item['holder_name'],
                    'holder_email' => $item['holder_email'],
                    'holder_phone' => $item['holder_phone'] ?: null,
                ]);
            }

            $order->update(['total_amount' => $total]);
        });

        return redirect()->route('front.checkout')->with('success', 'Order completed successfully.');
    }
}
