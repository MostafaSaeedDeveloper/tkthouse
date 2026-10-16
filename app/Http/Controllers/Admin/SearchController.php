<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Customer;
use App\Models\Event;
use App\Models\Order;
use App\Models\Ticket;
use Illuminate\Http\Request;

/** Topbar search. Each group only appears (and is only queried) when the user holds the matching permission. */
class SearchController extends Controller
{
    public function __invoke(Request $request)
    {
        $q = trim((string) $request->query('q', ''));
        $user = $request->user();
        $like = '%'.str_replace(['%', '_'], ['\%', '\_'], $q).'%';
        $results = ['events' => collect(), 'orders' => collect(), 'customers' => collect(), 'tickets' => collect()];

        if (mb_strlen($q) >= 2) {
            if ($user->can('events.view')) {
                $managed = $user->managedEvents?->pluck('id')->all() ?? [];
                $results['events'] = Event::query()->when($managed, fn ($e) => $e->whereIn('id', $managed))
                    ->where(fn ($e) => $e->where('name', 'like', $like)->orWhere('location', 'like', $like))->orderByDesc('event_date')->limit(8)->get();
            }
            if ($user->can('orders.view')) {
                $results['orders'] = Order::query()->visibleTo($user)->with(['customer', 'items.event'])
                    ->where(fn ($o) => $o->where('order_number', 'like', $like)
                        ->orWhereHas('customer', fn ($c) => $c->where('email', 'like', $like)->orWhere('first_name', 'like', $like)->orWhere('last_name', 'like', $like)->orWhere('phone', 'like', $like)))
                    ->latest()->limit(8)->get();
            }
            if ($user->can('attendees.view')) {
                $results['customers'] = Customer::query()
                    ->where(fn ($c) => $c->where('email', 'like', $like)->orWhere('first_name', 'like', $like)->orWhere('last_name', 'like', $like)->orWhere('phone', 'like', $like))
                    ->latest()->limit(8)->get();
            }
            if ($user->can('tickets.view')) {
                $results['tickets'] = Ticket::query()->where(fn ($t) => $t->where('ticket_number', 'like', $like)->orWhere('holder_name', 'like', $like)->orWhere('holder_email', 'like', $like))
                    ->latest()->limit(8)->get();
            }
        }

        return view('admin.search', ['q' => $q, 'results' => $results, 'total' => collect($results)->sum(fn ($r) => $r->count())]);
    }
}
