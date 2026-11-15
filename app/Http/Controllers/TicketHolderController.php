<?php

namespace App\Http\Controllers;

use App\Models\Order;
use App\Services\Tickets\TicketHolderService;
use Illuminate\Http\Request;

/** The buyer manages who each ticket of their order is for. Only the account that owns the order can do this (a holder cannot). */
class TicketHolderController extends Controller
{
    public function update(Request $request, string $orderNumber, string $ticket, TicketHolderService $holders)
    {
        [$order, $issued] = $this->resolve($request, $orderNumber, $ticket);

        $data = $request->validate([
            'holder_name' => ['required', 'string', 'max:255'],
            'holder_email' => ['required', 'email', 'max:255'],
        ]);

        $result = $holders->update($issued, $request->user(), $data['holder_name'], $data['holder_email']);

        $message = match (true) {
            $result['email_changed'] && $result['invited'] => 'Ticket moved. The previous e-mail no longer has access and a new link was sent to '.mb_strtolower(trim($data['holder_email'])).'.',
            $result['email_changed'] => 'Ticket moved, but we could not send the e-mail right now. Use "Resend link" to try again.',
            $result['name_changed'] => 'Holder name updated.',
            default => 'Nothing changed.',
        };

        return redirect()->to(route('front.account.orders.show', $order->order_number).'#t-'.$issued->id)->with($result['email_changed'] && ! $result['invited'] ? 'error' : 'success', $message);
    }

    public function resend(Request $request, string $orderNumber, string $ticket, TicketHolderService $holders)
    {
        [$order, $issued] = $this->resolve($request, $orderNumber, $ticket);

        $outcome = $holders->resend($issued, $request->user());

        return redirect()->to(route('front.account.orders.show', $order->order_number).'#t-'.$issued->id)
            ->with($outcome === 'ok' ? 'success' : 'error', $outcome === 'ok' ? 'Ticket link sent to '.$issued->holder_email.'.' : $outcome);
    }

    private function resolve(Request $request, string $orderNumber, string $uuid): array
    {
        $order = Order::query()->ownedByCustomer($request->user())->where('order_number', $orderNumber)->firstOrFail(); // somebody else's order does not exist
        $issued = $order->issuedTickets()->where('uuid', $uuid)->firstOrFail();

        return [$order, $issued];
    }
}
