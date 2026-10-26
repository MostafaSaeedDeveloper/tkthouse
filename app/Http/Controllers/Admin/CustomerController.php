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
        $managedEvents = $request->user()?->managedEvents ?? collect();
        $managedEventIds = $managedEvents->pluck('id')->all();
        $canFilterByEvent = $request->user()?->can('showing_orders') ?? false;

        $customersQuery = Customer::query()
            ->when(\App\Orders\OrderAccess::scoped($request->user()), fn (Builder $query) => $query->whereHas('orders', fn (Builder $o) => $o->visibleTo($request->user())))
            ->when($managedEvents->isNotEmpty(), function (Builder $query) use ($managedEventIds) {
                $query->whereHas('orders', function (Builder $ordersQuery) use ($managedEventIds) {
                    $ordersQuery->whereHas('items', fn (Builder $iq) => $iq->whereIn('event_id', $managedEventIds));
                });
            })
            ->withCount(['orders as orders_count' => function (Builder $ordersQuery) use ($managedEvents, $managedEventIds, $request) {
                $ordersQuery->visibleTo($request->user());
                if ($managedEvents->isNotEmpty()) {
                    $ordersQuery->whereHas('items', fn (Builder $iq) => $iq->whereIn('event_id', $managedEventIds));
                }
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
            ? Event::query()->orderBy('name')->get(['id', 'name', 'status'])
            : collect();

        $exportColumns = self::exportColumns();

        return view('admin.customers.index', compact('customers', 'events', 'canFilterByEvent', 'exportColumns'));
    }

    public function export(Request $request): StreamedResponse
    {
        abort_unless($this->isSuperAdmin($request->user()), 403);

        $managedEvents = $request->user()?->managedEvents ?? collect();
        $managedEventIds = $managedEvents->pluck('id')->all();
        $canFilterByEvent = $request->user()?->can('showing_orders') ?? false;

        $customersQuery = Customer::query()
            ->when($managedEvents->isNotEmpty(), function (Builder $query) use ($managedEventIds) {
                $query->whereHas('orders', function (Builder $ordersQuery) use ($managedEventIds) {
                    $ordersQuery->whereHas('items', fn (Builder $iq) => $iq->whereIn('event_id', $managedEventIds));
                });
            })
            ->withCount(['orders as orders_count' => function (Builder $ordersQuery) use ($managedEvents, $managedEventIds, $request) {
                $ordersQuery->visibleTo($request->user());
                if ($managedEvents->isNotEmpty()) {
                    $ordersQuery->whereHas('items', fn (Builder $iq) => $iq->whereIn('event_id', $managedEventIds));
                }
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

        $ids = array_filter(array_map('intval', (array) $request->input('ids', [])));
        if ($ids) {
            $customersQuery->whereIn('customers.id', $ids);
        }

        $available = self::exportColumns();
        $columns = array_values(array_intersect(array_keys($available), (array) $request->input('columns', [])));
        if (! $columns) {
            $columns = array_keys($available);
        }

        $customersQuery->withMax('orders as last_order_at', 'created_at');

        return response()->streamDownload(function () use ($customersQuery, $columns, $available) {
            $handle = fopen('php://output', 'w');
            fputcsv($handle, array_map(fn ($key) => $available[$key]['label'], $columns));
            foreach ($customersQuery->latest()->cursor() as $customer) {
                fputcsv($handle, array_map(fn ($key) => $available[$key]['value']($customer), $columns));
            }
            fclose($handle);
        }, 'customers-export.csv', ['Content-Type' => 'text/csv']);
    }

    /** @return array<string, array{label: string, value: \Closure}> */
    public static function exportColumns(): array
    {
        return [
            'name' => ['label' => 'Name', 'value' => fn ($c) => $c->full_name],
            'first_name' => ['label' => 'First Name', 'value' => fn ($c) => $c->first_name],
            'last_name' => ['label' => 'Last Name', 'value' => fn ($c) => $c->last_name],
            'email' => ['label' => 'Email', 'value' => fn ($c) => $c->email],
            'phone' => ['label' => 'Phone', 'value' => fn ($c) => $c->phone],
            'orders_count' => ['label' => 'Orders Count', 'value' => fn ($c) => $c->orders_count],
            'created_at' => ['label' => 'Customer Since', 'value' => fn ($c) => $c->created_at?->format('Y-m-d H:i')],
            'last_order_at' => ['label' => 'Last Order', 'value' => fn ($c) => $c->last_order_at ? \Illuminate\Support\Carbon::parse($c->last_order_at)->format('Y-m-d H:i') : ''],
        ];
    }

    public function show(Request $request, Customer $customer)
    {
        $managedEvents = $request->user()?->managedEvents ?? collect();
        $managedEventIds = $managedEvents->pluck('id')->all();

        if (\App\Orders\OrderAccess::scoped($request->user())) {
            abort_unless($customer->orders()->visibleTo($request->user())->exists(), 404);
        }

        if ($managedEvents->isNotEmpty()) {
            $hasVisibleOrder = $customer->orders()
                ->whereHas('items', function (Builder $itemsQuery) use ($managedEventIds) {
                    $itemsQuery->whereIn('event_id', $managedEventIds);
                })
                ->exists();

            abort_unless($hasVisibleOrder, 403);
        }

        $customer->load(['orders' => function ($query) use ($managedEvents, $managedEventIds, $request) {
            $query->visibleTo($request->user());
            if ($managedEvents->isNotEmpty()) {
                $query->whereHas('items', fn (Builder $iq) => $iq->whereIn('event_id', $managedEventIds));
            }
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

    private function isSuperAdmin(?\App\Models\User $user): bool
    {
        if (! $user) {
            return false;
        }
        $normalize = fn ($val) => strtolower((string) preg_replace('/[^a-z0-9]/i', '', trim((string) $val)));

        if ($normalize($user->username) === 'superadmin') {
            return true;
        }

        return $user->roles->contains(fn ($role) =>
            $normalize($role->name) === 'superadmin'
        );
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
