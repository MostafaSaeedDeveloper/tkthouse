<?php

namespace App\Http\Controllers;

use App\Models\IssuedTicket;
use App\Models\Order;
use App\Models\PaymentMethod;
use App\Services\Auth\EmailChangeService;
use App\Services\Checkout\CheckoutIdentity;
use App\Services\Invoices\InvoiceService;
use App\Services\PendingPaymentExpiryService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * Customer portal: what a signed-in customer may see is decided by Order::ownedByCustomer / IssuedTicket::ownedByCustomer
 * (bound to the account, never to "an e-mail somebody typed at checkout").
 */
class CustomerDashboardController extends Controller
{
    private const ORDER_FILTERS = [
        'all' => 'All',
        'paid' => 'Paid',
        'pending' => 'Pending',
        'failed' => 'Failed',
        'canceled' => 'Canceled',
        'refunded' => 'Refunded',
    ];

    /* ------------------------------------------------------------ account */

    public function profile(Request $request, EmailChangeService $emailChange)
    {
        $user = $request->user();

        return view('front.account.profile', [
            'user' => $user,
            'passwordless' => $user->passwordless_since !== null,
            'pendingEmail' => $emailChange->pendingEmail($user),
            'freshOtp' => (int) $request->session()->get('auth.otp_verified_at', 0) >= now()->subMinutes(30)->timestamp,
            'orderCount' => Order::query()->ownedByCustomer($user)->count(),
            'ticketCount' => IssuedTicket::query()->ownedByCustomer($user)->count(),
        ]);
    }

    public function updateProfile(Request $request)
    {
        $user = $request->user();

        // The e-mail is deliberately not editable here: changing it moves ownership of orders and tickets (see requestEmailChange).
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'phone' => ['nullable', 'string', 'max:40'],
            'profile_image' => ['nullable', 'file', 'mimes:jpg,jpeg,png,webp', 'max:2048'],
            'remove_profile_image' => ['nullable', 'boolean'],
        ]);

        $update = ['name' => trim($validated['name']), 'phone' => filled($validated['phone'] ?? null) ? trim($validated['phone']) : null];

        if ($request->hasFile('profile_image') || $request->boolean('remove_profile_image')) {
            // Only files we stored ourselves are deleted; an external (http) avatar URL is just unlinked.
            if ($user->profile_image && ! Str::startsWith($user->profile_image, ['http://', 'https://']) && Storage::disk('public')->exists($user->profile_image)) {
                Storage::disk('public')->delete($user->profile_image);
            }

            $update['profile_image'] = $request->hasFile('profile_image')
                ? $request->file('profile_image')->store('profile-images', 'public')
                : null;
        }

        $user->update($update);

        return redirect()->route('front.account.profile')->with('success', 'Your details were saved.');
    }

    public function requestEmailChange(Request $request, EmailChangeService $emailChange, CheckoutIdentity $identity)
    {
        $user = $request->user();
        abort_if($identity->isStaff($user), 403);

        $data = $request->validate([
            'email' => ['required', 'email', 'max:255'],
            'current_password' => ['nullable', 'string'],
        ]);

        // Prove the person at the keyboard first: the password for accounts that have one, a recent e-mail-code sign-in otherwise.
        if ($user->passwordless_since === null) {
            if (! filled($data['current_password'] ?? null) || ! Hash::check($data['current_password'], (string) $user->password)) {
                return response()->json(['ok' => false, 'message' => 'Enter your current password to change your email.'], 422);
            }
        } elseif ((int) $request->session()->get('auth.otp_verified_at', 0) < now()->subMinutes(30)->timestamp) {
            return response()->json(['ok' => false, 'message' => 'For your security, sign out and sign in again with an email code, then change your email.'], 403);
        }

        if ($emailChange->request($user, $data['email']) === 'limited') {
            return response()->json(['ok' => false, 'message' => 'Too many requests. Please try again later.'], 429);
        }

        return response()->json(['ok' => true, 'message' => 'If this address can be used, a 6-digit code is on its way. Your email changes only after you enter it.']);
    }

    public function confirmEmailChange(Request $request, EmailChangeService $emailChange)
    {
        $data = $request->validate(['code' => ['required', 'string', 'max:10']]);

        if (! $emailChange->confirm($request->user(), trim($data['code']))) {
            return response()->json(['ok' => false, 'message' => 'That code is not valid or has expired.'], 422);
        }

        return response()->json(['ok' => true, 'message' => 'Your email was updated and verified.', 'email' => $request->user()->fresh()->email]);
    }

    /* ------------------------------------------------------------ orders */

    public function orders(Request $request)
    {
        app(PendingPaymentExpiryService::class)->expireDueOrders();

        $user = $request->user();
        $filter = array_key_exists((string) $request->query('status'), self::ORDER_FILTERS) ? (string) $request->query('status') : 'all';
        $search = trim((string) $request->query('q', ''));

        $query = Order::query()->ownedByCustomer($user)->with(['items.event', 'paymentAttempts', 'issuedTickets']);

        match ($filter) {
            'paid' => $query->where('status', 'paid'),
            'pending' => $query->whereIn('status', ['pending_payment', 'pending_approval', 'on_hold'])
                ->whereNotExists(fn ($q) => $q->selectRaw('1')->from('payment_attempts')->whereColumn('payment_attempts.order_id', 'orders.id')->where('payment_attempts.status', 'failed')
                    ->whereNotExists(fn ($open) => $open->selectRaw('1')->from('payment_attempts as pa2')->whereColumn('pa2.order_id', 'orders.id')->where('pa2.status', 'initiated'))),
            'failed' => $query->where('status', 'pending_payment')
                ->whereExists(fn ($q) => $q->selectRaw('1')->from('payment_attempts')->whereColumn('payment_attempts.order_id', 'orders.id')->where('payment_attempts.status', 'failed'))
                ->whereNotExists(fn ($q) => $q->selectRaw('1')->from('payment_attempts as pa3')->whereColumn('pa3.order_id', 'orders.id')->where('pa3.status', 'initiated')),
            'canceled' => $query->whereIn('status', ['canceled', 'cancelled', 'rejected', 'expired']),
            'refunded' => $query->whereIn('status', ['refunded', 'partially_refunded']),
            default => null,
        };

        if ($search !== '') {
            $like = '%'.str_replace(['%', '_'], ['\%', '\_'], $search).'%';
            $query->where(function ($q) use ($like) {
                $q->where('order_number', 'like', $like)
                    ->orWhereHas('items', fn ($i) => $i->where('ticket_name', 'like', $like)->orWhereHas('event', fn ($e) => $e->where('name', 'like', $like)));
            });
        }

        $orders = $query->latest()->paginate(8)->withQueryString();

        return view('front.account.orders', [
            'user' => $user,
            'orders' => $orders,
            'filter' => $filter,
            'filters' => self::ORDER_FILTERS,
            'search' => $search,
        ]);
    }

    public function showOrder(Request $request, string $orderNumber)
    {
        app(PendingPaymentExpiryService::class)->expireDueOrders();

        $order = Order::query()->ownedByCustomer($request->user())->where('order_number', $orderNumber)
            ->with(['items.event', 'customer', 'paymentAttempts', 'issuedTickets.dashboardTicket.event', 'issuedTickets.orderItem.event'])
            ->firstOrFail(); // somebody else's order simply does not exist

        $method = PaymentMethod::query()->where('code', (string) $order->payment_method)->first();

        return view('front.account.order-show', [
            'user' => $request->user(),
            'order' => $order,
            'paymentMethodLabel' => $method ? trim((string) ($method->checkout_label ?: $method->name)) : null,
        ]);
    }

    /** Invoice PDF of a paid order. Same ownership rule as the order page: somebody else's order simply does not exist. */
    public function invoice(Request $request, string $orderNumber, InvoiceService $invoices)
    {
        $order = Order::query()->ownedByCustomer($request->user())->where('order_number', $orderNumber)
            ->with(['customer', 'items'])
            ->firstOrFail();

        abort_unless($invoices->isInvoiceable($order), 404);

        return response($invoices->pdf($order), 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'attachment; filename="'.$invoices->filename($order).'"',
            'Cache-Control' => 'private, no-store',
        ]);
    }

    /* ------------------------------------------------------------ tickets */

    /** My Tickets: every ticket the account may open, sorted into Upcoming / Past / Used / Canceled & Refunded (see CustomerPortal::ticketGroup). */
    public function tickets(Request $request)
    {
        $user = $request->user();
        $orderNumber = trim((string) $request->query('order', ''));
        $groupsKeys = ['upcoming', 'past', 'used', 'canceled'];
        $requested = (string) $request->query('when', '');
        $when = in_array($requested, [...$groupsKeys, 'all'], true) ? $requested : ($orderNumber !== '' ? 'all' : 'upcoming');

        $query = IssuedTicket::query()->ownedByCustomer($user)->with(['order', 'dashboardTicket.event', 'orderItem.event']);
        $forOthers = 0;
        if ($orderNumber !== '') {
            // The order's own list (the buyer's "View your tickets" link): every ticket of that order the account may open.
            $query->whereHas('order', fn ($o) => $o->where('order_number', $orderNumber));
        } else {
            // My Tickets = tickets that are *mine* (assigned to my e-mail, or bought by me with no other holder). Tickets I bought for other
            // people live under My Orders, so nothing is listed twice and nobody's ticket is shown as mine. A ticket is a single row whoever bought it.
            $mine = fn ($q) => $q->whereRaw('LOWER(holder_email) = ?', [mb_strtolower((string) $user->email)])
                ->orWhere(fn ($w) => $w->where(fn ($b) => $b->whereNull('holder_email')->orWhere('holder_email', ''))->whereHas('order', fn ($o) => $o->ownedByCustomer($user)));
            $forOthers = (clone $query)->whereNot(fn ($q) => $mine($q))->count();
            $query->where(fn ($q) => $mine($q));
        }

        $tickets = $query->orderByDesc('id')->get();
        $byGroup = $tickets->groupBy(fn (IssuedTicket $t) => \App\Support\CustomerPortal::ticketGroup($t, $t->event()));
        $counts = collect($groupsKeys)->mapWithKeys(fn ($k) => [$k => $byGroup->get($k, collect())->count()])->all();

        // "Upcoming" first, then the rest, each list grouped per order so tickets bought together stay together.
        $visible = $when === 'all' ? $groupsKeys : [$when];
        $sections = collect($visible)->mapWithKeys(fn ($k) => [$k => $byGroup->get($k, collect())
            ->groupBy('order_id')->map(fn ($rows) => ['order' => $rows->first()->order, 'tickets' => $rows->sortBy('id')->values()])->values()])
            ->filter(fn ($groups) => $groups->isNotEmpty());

        return view('front.account.tickets', [
            'user' => $user,
            'sections' => $sections,
            'counts' => $counts,
            'total' => $tickets->count(),
            'when' => $when,
            'orderNumber' => $orderNumber,
            'forOthers' => $forOthers,
        ]);
    }
}
