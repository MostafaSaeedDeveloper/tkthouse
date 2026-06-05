<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Event;
use App\Models\Customer;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\StreamedResponse;

class CustomerController extends Controller
{
    public function index(Request $request)
    {
        $managedEvent = $request->user()?->managedEvent;
        $canFilterByEvent = $request->user()?->can('showing_orders') ?? false;

        $customersQuery = Customer::query()
            ->when($managedEvent, function (Builder $query) use ($managedEvent) {
                $query->whereHas('orders', function (Builder $ordersQuery) use ($managedEvent) {
                    $this->applyEventScopeToOrdersQuery($ordersQuery, $managedEvent);
                });
            })
            ->withCount(['orders as orders_count' => function (Builder $ordersQuery) use ($managedEvent) {
                $this->applyEventScopeToOrdersQuery($ordersQuery, $managedEvent);
            }]);

        if ($request->filled('search')) {
            $search = trim((string) $request->input('search'));
            $customersQuery->where(function (Builder $q) use ($search) {
                $q->where('first_name', 'like', "%{$search}%")
                    ->orWhere('last_name', 'like', "%{$search}%")
                    ->orWhere('phone', 'like', "%{$search}%");
            });
        }

        if ($canFilterByEvent && $request->filled('event_id')) {
            $eventId = $request->integer('event_id');
            $customersQuery->whereHas('orders', function (Builder $q) use ($eventId) {
                $q->whereHas('items', fn (Builder $iq) => $iq->where('event_id', $eventId));
            });
        }

        $customers = $customersQuery->latest()->paginate(15)->withQueryString();

        $events = $canFilterByEvent
            ? Event::query()->orderBy('name')->get(['id', 'name'])
            : collect();

        return view('admin.customers.index', compact('customers', 'events', 'canFilterByEvent'));
    }

    public function export(Request $request): StreamedResponse
    {
        abort_unless($this->isSuperAdminOrAdmin($request->user()), 403);

        $managedEvent = $request->user()?->managedEvent;
        $canFilterByEvent = $request->user()?->can('showing_orders') ?? false;

        $customersQuery = Customer::query()
            ->when($managedEvent, function (Builder $query) use ($managedEvent) {
                $query->whereHas('orders', function (Builder $ordersQuery) use ($managedEvent) {
                    $this->applyEventScopeToOrdersQuery($ordersQuery, $managedEvent);
                });
            })
            ->withCount(['orders as orders_count' => function (Builder $ordersQuery) use ($managedEvent) {
                $this->applyEventScopeToOrdersQuery($ordersQuery, $managedEvent);
            }]);

        if ($request->filled('search')) {
            $search = trim((string) $request->input('search'));
            $customersQuery->where(function (Builder $q) use ($search) {
                $q->where('first_name', 'like', "%{$search}%")
                    ->orWhere('last_name', 'like', "%{$search}%")
                    ->orWhere('phone', 'like', "%{$search}%");
            });
        }

        if ($canFilterByEvent && $request->filled('event_id')) {
            $eventId = $request->integer('event_id');
            $customersQuery->whereHas('orders', function (Builder $q) use ($eventId) {
                $q->whereHas('items', fn (Builder $iq) => $iq->where('event_id', $eventId));
            });
        }

        $customers = $customersQuery->latest()->get();

        return response()->streamDownload(function () use ($customers) {
            $handle = fopen('php://output', 'w');
            fputcsv($handle, ['Name', 'Email', 'Phone', 'Orders Count']);
            foreach ($customers as $customer) {
                fputcsv($handle, [
                    $customer->full_name,
                    $customer->email,
                    $customer->phone,
                    $customer->orders_count,
                ]);
            }
            fclose($handle);
        }, 'customers-export.csv', ['Content-Type' => 'text/csv']);
    }

    public function show(Request $request, Customer $customer)
    {
        $managedEvent = $request->user()?->managedEvent;

        if ($managedEvent) {
            $hasVisibleOrder = $customer->orders()
                ->whereHas('items', function (Builder $itemsQuery) use ($managedEvent) {
                    $itemsQuery->where('event_id', $managedEvent->id);
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
            $itemsQuery->where('event_id', $event->id);
        });
    }

    private function isSuperAdminOrAdmin(?\App\Models\User $user): bool
    {
        if (! $user) {
            return false;
        }
        $normalize = fn ($val) => strtolower((string) preg_replace('/[^a-z0-9]/i', '', trim((string) $val)));

        if ($normalize($user->username) === 'superadmin') {
            return true;
        }

        return $user->roles->contains(fn ($role) =>
            in_array($normalize($role->name), ['superadmin', 'admin'], true)
        );
    }
}
