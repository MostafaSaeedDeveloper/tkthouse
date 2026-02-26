<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Customer;
use App\Models\Event;
use App\Models\EventTicket;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Ticket;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class TicketController extends Controller
{
    public function index()
    {
        $tickets = Ticket::with(['event', 'customer', 'order'])
            ->latest()
            ->paginate(20);

        return view('admin.tickets.index', compact('tickets'));
    }

    public function create()
    {
        return view('admin.tickets.create', [
            'events' => Event::orderBy('name')->get(),
            'customers' => Customer::orderBy('first_name')->get(),
            'orders' => Order::orderByDesc('id')->get(),
            'eventTickets' => EventTicket::orderBy('name')->get(),
            'orderItems' => OrderItem::orderByDesc('id')->get(),
        ]);
    }

    public function store(Request $request)
    {
        $validated = $this->validateTicket($request);

        Ticket::create([
            ...$validated,
            'ticket_code' => $validated['ticket_code'] ?? ('TKT-' . Str::upper(Str::random(10))),
            'issued_at' => $validated['issued_at'] ?? now(),
        ]);

        return redirect()->route('admin.tickets.index')->with('success', 'Ticket created successfully.');
    }

    public function show(Ticket $ticket)
    {
        $ticket->load(['event', 'customer', 'order', 'orderItem', 'eventTicket']);

        return view('admin.tickets.show', compact('ticket'));
    }

    public function edit(Ticket $ticket)
    {
        return view('admin.tickets.edit', [
            'ticket' => $ticket,
            'events' => Event::orderBy('name')->get(),
            'customers' => Customer::orderBy('first_name')->get(),
            'orders' => Order::orderByDesc('id')->get(),
            'eventTickets' => EventTicket::orderBy('name')->get(),
            'orderItems' => OrderItem::orderByDesc('id')->get(),
        ]);
    }

    public function update(Request $request, Ticket $ticket)
    {
        $validated = $this->validateTicket($request, $ticket->id);

        $ticket->update($validated);

        return redirect()->route('admin.tickets.index')->with('success', 'Ticket updated successfully.');
    }

    public function destroy(Ticket $ticket)
    {
        $ticket->delete();

        return redirect()->route('admin.tickets.index')->with('success', 'Ticket deleted successfully.');
    }

    private function validateTicket(Request $request, ?int $ticketId = null): array
    {
        return $request->validate([
            'ticket_code' => ['nullable', 'string', 'max:255', 'unique:tickets,ticket_code,' . $ticketId],
            'order_id' => ['required', 'exists:orders,id'],
            'order_item_id' => ['required', 'exists:order_items,id'],
            'customer_id' => ['required', 'exists:customers,id'],
            'event_id' => ['required', 'exists:events,id'],
            'event_ticket_id' => ['required', 'exists:event_tickets,id'],
            'ticket_name' => ['required', 'string', 'max:255'],
            'price' => ['required', 'numeric', 'min:0'],
            'status' => ['required', 'in:active,used,cancelled'],
            'issued_at' => ['nullable', 'date'],
            'used_at' => ['nullable', 'date'],
        ]);
    }
}
