<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Event;
use App\Models\Customer;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;

class CustomerController extends Controller
{
    public function index(Request $request)
    {
        $managedEvent = $request->user()?->managedEvent;

        $customers = Customer::query()
            ->when($managedEvent, function (Builder $query) use ($managedEvent) {
                $query->whereHas('orders', function (Builder $ordersQuery) use ($managedEvent) {
                    $this->applyEventScopeToOrdersQuery($ordersQuery, $managedEvent);
                });
            })
            ->withCount(['orders as orders_count' => function (Builder $ordersQuery) use ($managedEvent) {
                $this->applyEventScopeToOrdersQuery($ordersQuery, $managedEvent);
            }])
            ->latest()
            ->paginate(15);

        return view('admin.customers.index', compact('customers'));
    }

    public function show(Request $request, Customer $customer)
    {
        $managedEvent = $request->user()?->managedEvent;

        if ($managedEvent) {
            $hasVisibleOrder = $customer->orders()
                ->whereHas('items', function (Builder $itemsQuery) use ($managedEvent) {
                    $itemsQuery->where(function (Builder $ticketQuery) use ($managedEvent) {
                        $ticketQuery->where('ticket_name', 'like', $managedEvent->name.' - %')
                            ->orWhere('ticket_name', $managedEvent->name);
                    });
                })
                ->exists();

            abort_unless($hasVisibleOrder, 403);
        }

        $customer->load(['orders' => function ($query) use ($managedEvent) {
            $this->applyEventScopeToOrdersQuery($query, $managedEvent);
            $query->latest()->withCount('items');
        }]);

        return view('admin.customers.show', compact('customer'));
    }

    private function applyEventScopeToOrdersQuery($query, ?Event $event): void
    {
        if (! $event) {
            return;
        }

        $query->whereHas('items', function (Builder $itemsQuery) use ($event) {
            $itemsQuery->where(function (Builder $ticketQuery) use ($event) {
                $ticketQuery->where('ticket_name', 'like', $event->name.' - %')
                    ->orWhere('ticket_name', $event->name);
            });
        });
    }
}
