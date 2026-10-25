<?php

namespace App\Http\Controllers;

use App\Models\Customer;
use App\Models\Event;
use App\Models\Order;
use App\Models\PaymentMethod;
use App\Services\Auth\GuestOrderAccess;
use App\Services\Checkout\CheckoutIdentity;
use App\Services\Checkout\CheckoutPricing;
use App\Services\Checkout\CheckoutSelection;
use App\Services\Checkout\EmailVerificationRequired;
use App\Services\Checkout\OrderPlacementService;
use App\Services\Checkout\StaffEmailNotAllowed;
use App\Services\Payments\PaymentGatewayManager;
use App\Services\Payments\PaymentService;
use Illuminate\Contracts\Cache\LockTimeoutException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * Express Checkout. Step 1 (tickets) lives on the event page; everything else is ONE page:
 *   GET  /checkout                 contact + attendees + promo + payment + summary
 *   POST /checkout                 places the order (AJAX or plain form), no login required
 *   POST /checkout/promo           server-side promo preview
 *   GET  /checkout/confirmation/N  result, rendered from the order's real status
 *
 * No login is needed, but a typed e-mail is never proof of an account: see CheckoutIdentity.
 * Prices, fees and discounts are always recomputed on the server.
 */
class CheckoutController extends Controller
{
    private const SELECTION = 'checkout.event_selection';
    private const DRAFT = 'checkout.draft';
    private const PROMO = 'checkout.promo';
    private const SELECTION_ID = 'checkout.selection_id';
    private const NEW_ACCOUNT_ORDERS = 'checkout.new_account_orders';

    public function __construct(
        private readonly CheckoutSelection $selection,
        private readonly CheckoutPricing $pricing,
    ) {
    }

    /* ------------------------------------------------------------------ the page */

    public function index(Request $request, PaymentGatewayManager $gateways)
    {
        if ($request->filled('event') || $request->filled('cart')) {
            if (! $request->filled('event') || ! $request->filled('cart')) {
                return $this->backToEvents('Please choose an event and tickets first.');
            }

            $event = Event::query()->findOrFail((int) $request->input('event'));

            try {
                $selection = $this->selection->fromEncodedCart($event, (string) $request->input('cart'));
            } catch (ValidationException $e) {
                return redirect()->route('front.events.show', $event)->with('error', $this->firstError($e, 'Your ticket selection is not valid.'));
            }

            $this->startSelection($selection);

            return redirect()->route('front.checkout'); // clean URL: refresh/back stay on this page
        }

        if (! $context = $this->context()) {
            // Back button from the payment page right after ordering: continue that payment instead of losing it.
            if ($recent = $this->recentUnpaidOrder($request)) {
                return redirect()->route('front.orders.payment', ['order' => $recent, 'token' => $recent->payment_link_token]);
            }

            return $this->invalidContextRedirect();
        }

        [$event, $selection] = $context;
        $units = collect($selection['units']);
        $draft = (array) session(self::DRAFT, []);
        $user = $request->user();
        $defaults = $user ? $this->buyerDefaults($user) : [];
        $buyer = array_merge($defaults, array_filter((array) ($draft['buyer'] ?? []), fn ($v) => $v !== null && $v !== ''));
        $promoCode = (string) session(self::PROMO, '');
        $promoNotice = null;

        try {
            $quote = $this->pricing->quote($event, $units, $promoCode);
        } catch (ValidationException $e) {
            session()->forget(self::PROMO);
            $promoNotice = $this->firstError($e, 'The promo code is no longer valid.');
            $promoCode = '';
            $quote = $this->pricing->quote($event, $units);
        }

        // Provisional routing: the agent/merchant account (and so the methods) shown to this customer, fixed for this session.
        $routing = null;
        if (! $event->requires_booking_approval) {
            $routingSvc = app(\App\Services\Payments\Routing\CheckoutRoutingService::class);
            if ($routing = $routingSvc->reserve(session(\App\Services\Payments\Routing\CheckoutRoutingService::SESSION_KEY), $request->session()->getId())) {
                session([\App\Services\Payments\Routing\CheckoutRoutingService::SESSION_KEY => $routing->token]);
            }
        }
        $methods = $routing ? app(\App\Services\Payments\Routing\CheckoutRoutingService::class)->methods($routing) : app(\App\Services\Payments\Routing\CheckoutRoutingService::class)->methodsWithoutReservation();
        // A saved choice that this customer's account no longer offers is dropped (never silently swapped).
        $savedMethod = (string) ($draft['payment_method'] ?? '');
        $savedMethod = $methods->contains('code', $savedMethod) ? $savedMethod : '';

        return view('front.checkout.express', [
            'event' => $event,
            'groups' => $this->groups($units),
            'wizard' => $this->experience($request) === 'wizard',
            'preflightEmail' => (string) session('checkout.preflight_email', ''),
            'canCompare' => ! app()->isProduction(),
            'quote' => $quote,
            'buyer' => $buyer,
            'attendees' => (array) ($draft['attendees'] ?? []),
            'methods' => $methods,
            'selectedMethod' => $savedMethod,
            'requiresApproval' => (bool) $event->requires_booking_approval,
            'promoCode' => $promoCode,
            'promoNotice' => $promoNotice,
            'signedIn' => (bool) $user,
            'verifyEmail' => session('checkout_verify_email'),
            'step' => 2,
        ]);
    }

    /** Previews a promo code. The discount is computed here, never in the browser. */
    public function applyPromo(Request $request)
    {
        if (! $context = $this->context()) {
            return $request->expectsJson()
                ? response()->json(['ok' => false, 'message' => 'Your checkout session expired.'], 409)
                : $this->invalidContextRedirect();
        }

        [$event, $selection] = $context;
        $units = collect($selection['units']);

        if ($request->boolean('remove') || trim((string) $request->input('promo_code')) === '') {
            session()->forget(self::PROMO);
            $quote = $this->pricing->quote($event, $units);

            return $request->expectsJson()
                ? response()->json(['ok' => true, 'message' => 'Promo code removed.', 'quote' => $quote->toArray()])
                : redirect()->route('front.checkout');
        }

        $code = strtoupper(trim((string) $request->input('promo_code')));

        try {
            $quote = $this->pricing->quote($event, $units, $code);
        } catch (ValidationException $e) {
            $message = $this->firstError($e, 'Promo code is invalid.');

            return $request->expectsJson()
                ? response()->json(['ok' => false, 'message' => $message], 422)
                : redirect()->route('front.checkout')->withErrors(['promo_code' => $message])->withInput();
        }

        session([self::PROMO => $code]);

        return $request->expectsJson()
            ? response()->json(['ok' => true, 'message' => "Promo code {$code} applied.", 'quote' => $quote->toArray()])
            : redirect()->route('front.checkout');
    }

    /* ------------------------------------------------------------------ placing the order */

    public function store(Request $request, PaymentGatewayManager $gateways, OrderPlacementService $placement, CheckoutIdentity $identity)
    {
        if (! $context = $this->context()) {
            // Resubmit / back button right after a successful submit: take the customer to the order just created.
            if ($recent = $this->recentOrder($request)) {
                return $this->respondWith($request, $this->afterPlacementUrl($recent, $gateways));
            }

            return $this->respondInvalid($request);
        }

        [$event, $selection] = $context;
        $selectionId = (string) session(self::SELECTION_ID, '');
        $units = collect($selection['units']);

        // What the customer typed is kept (refresh, validation error, coming back from the payment page).
        session([self::DRAFT => [
            'buyer' => $this->buyerInput($request),
            'attendees' => $this->attendeeInput($request),
            'payment_method' => (string) $request->input('payment_method', ''),
        ]]);

        $doneKey = "checkout-done:{$selectionId}";
        if ($selectionId !== '' && ($existing = $this->findDoneOrder($request, $doneKey))) {
            return $this->respondWith($request, $this->afterPlacementUrl($existing, $gateways));
        }

        $lock = Cache::lock('checkout-lock:'.$request->session()->getId(), 20);
        try {
            $lock->block(8);
        } catch (LockTimeoutException) {
            return $this->respondError($request, 'Your order is already being processed. Please wait a moment.', 429);
        }

        try {
            if ($selectionId !== '' && ($existing = $this->findDoneOrder($request, $doneKey))) {
                return $this->respondWith($request, $this->afterPlacementUrl($existing, $gateways));
            }

            // Validate everything first so the customer sees all problems at once.
            $errors = [];
            $buyer = $attendees = null;
            try {
                $buyer = $this->validateBuyer($request);
            } catch (ValidationException $e) {
                $errors = $e->errors();
            }
            try {
                $attendees = $this->validateAttendees((array) $request->input('attendees', []), $units->count());
            } catch (ValidationException $e) {
                $errors = array_merge($errors, $e->errors());
            }

            $requiresApproval = (bool) $event->requires_booking_approval;
            $methodCode = null;
            if (! $requiresApproval) {
                $methodCode = trim((string) $request->input('payment_method', ''));
                if ($methodCode === '') {
                    $errors['payment_method'] = ['Please choose a payment method.'];
                } elseif (! $this->methodOffered($request, $gateways, $methodCode)) {
                    $errors['payment_method'] = ['The selected payment method is not available.'];
                }
            }

            if ($errors) {
                throw ValidationException::withMessages($errors);
            }

            // A stale price must not leave a new account behind: check it before the identity step
            // (OrderPlacementService repeats the check inside its transaction).
            $promo = trim((string) $request->input('promo_code', session(self::PROMO, '')));
            $expected = $request->filled('expected_total') ? (float) $request->input('expected_total') : null;
            if ($expected !== null) {
                try {
                    $current = $this->pricing->quote($event, collect($this->selection->revalidate($event, $units)['units']), $promo !== '' ? $promo : null);
                    if (abs($current->total - $expected) > 0.009) {
                        throw ValidationException::withMessages(['price_changed' => 'The price changed while you were checking out. Please review your order again.']);
                    }
                } catch (ValidationException $e) {
                    return $this->handlePlacementError($request, $e, $event);
                }
            }

            // Who does this order belong to? Never decided by a typed e-mail alone.
            try {
                [$user, $newAccount] = $identity->resolve($request, $buyer);
            } catch (EmailVerificationRequired $e) {
                return $this->respondVerification($request, $e->email);
            } catch (StaffEmailNotAllowed $e) {
                throw ValidationException::withMessages(['email' => $e->getMessage()]);
            }

            try {
                $order = $placement->place(
                    $user, $event, $units, $buyer, $attendees,
                    $promo !== '' ? $promo : null, $methodCode, $expected, $this->affiliateUserId($request, $user),
                    session(\App\Services\Payments\Routing\CheckoutRoutingService::SESSION_KEY)
                );
            } catch (ValidationException $e) {
                return $this->handlePlacementError($request, $e, $event);
            }

            if (! $request->user()) {
                // The mailbox is not proven: keep this order out of account pages until the owner confirms it with a code.
                $order->forceFill(['owner_unverified' => true])->saveQuietly();
            }
            GuestOrderAccess::grant($order);
            if ($newAccount) {
                session([self::NEW_ACCOUNT_ORDERS => array_values(array_unique(array_merge((array) session(self::NEW_ACCOUNT_ORDERS, []), [$order->id])))]);
            }
            if ($selectionId !== '') {
                Cache::put($doneKey, $order->order_number, now()->addHour());
            }
            $this->clearCheckoutSession();
            session(['checkout.last_order' => $order->order_number]);

            return $this->respondWith($request, $this->afterPlacementUrl($order, $gateways));
        } finally {
            $lock->release();
        }
    }

    /* ------------------------------------------------------------------ confirmation */

    /**
     * "Wrong email?": the browser that just placed a guest order may correct the address of the account it created,
     * as long as nobody proved that mailbox yet. Existing/staff addresses are refused with one generic answer.
     */
    public function changeEmail(Request $request, string $orderNumber, CheckoutIdentity $identity)
    {
        $order = Order::query()->where('order_number', $orderNumber)->with('user', 'customer')->first();
        abort_unless($order && GuestOrderAccess::can($request, $order) && $order->owner_unverified, 404);

        $account = $identity->sessionCreatedAccount($request);
        abort_unless($account && (int) $account->id === (int) $order->user_id, 403);

        $data = $request->validate(['email' => ['required', 'email', 'max:255']]);
        $email = \App\Services\Auth\EmailOtpService::normalize($data['email']);

        $taken = \App\Services\Auth\EmailOtpService::findUser($email);
        if ($taken && (int) $taken->id !== (int) $account->id) {
            return response()->json(['ok' => false, 'message' => 'This address can\'t be used for this booking. Please use a different email.'], 422);
        }

        DB::transaction(function () use ($account, $email, $order) {
            $account->forceFill(['email' => $email, 'email_verified_at' => null])->save();
            $customer = \App\Models\Customer::query()->firstOrCreate(['email' => $email], [
                'first_name' => $order->customer?->first_name, 'last_name' => $order->customer?->last_name, 'phone' => $order->customer?->phone,
            ]);
            Order::query()->where('user_id', $account->id)->where('owner_unverified', true)
                ->whereIn('id', (array) session('checkout.my_orders', []))->update(['customer_id' => $customer->id]);
        });

        return response()->json(['ok' => true, 'email' => $email, 'masked' => GuestOrderAccess::mask($email)]);
    }

    /** Legacy URL (older emails / gateway return links): resolve to the real, status-driven confirmation page. */
    public function thankYou(Request $request)
    {
        $orderNumber = trim((string) $request->query('order'));
        $order = $orderNumber !== '' ? Order::query()->where('order_number', $orderNumber)->first() : null;

        if ($order && GuestOrderAccess::can($request, $order)) {
            return redirect()->route('front.checkout.confirmation', $orderNumber);
        }

        return redirect()->route($request->user() ? 'front.account.orders' : 'front.events');
    }

    /** What is shown comes from the order's real status, never from a query string, so a URL can not fake a payment. */
    /** Lightweight check used by the confirmation page while a payment is pending: asks the gateway (every 2 s at most) and answers with the state only. */
    public function confirmationStatus(Request $request, string $orderNumber, PaymentService $payments)
    {
        $order = Order::query()->where('order_number', $orderNumber)->first();
        abort_unless($order && GuestOrderAccess::can($request, $order), 404);

        $this->refreshPendingPayment($order, $payments, 2);

        return response()->json(['state' => $this->confirmationState($order->refresh())])->header('Cache-Control', 'no-store');
    }

    /** The customer may be back before the gateway callback: ask the gateway instead of guessing. */
    private function refreshPendingPayment(Order $order, PaymentService $payments, int $minSeconds = 2): void
    {
        if ($order->status === 'pending_payment' && $order->paymentAttempts()->exists() && Cache::add('order-refresh:'.$order->id, 1, $minSeconds)) {
            try {
                $payments->refresh($order);
            } catch (\Throwable $e) {
                Log::warning('Confirmation refresh failed.', ['order_id' => $order->id, 'error' => $e->getMessage()]);
            }
            $order->refresh();
        }
    }

    /** paid | review | closed | failed | unpaid | pending | other. A failure the gateway reported only by webhook counts as failed too. */
    private function confirmationState(Order $order): string
    {
        $attempts = $order->paymentAttempts()->with('failedWebhookEvent')->latest('id')->get();
        $latest = $attempts->first();
        $hasOpen = $attempts->contains(fn ($a) => $a->displayStatus() === 'initiated');

        return match (true) {
            $order->status === 'paid' => 'paid',
            $order->status === 'pending_approval' => 'review',
            in_array($order->status, ['canceled', 'rejected'], true) => 'closed',
            $order->status === 'pending_payment' && $latest?->displayStatus() === 'failed' && ! $hasOpen => 'failed',
            $order->status === 'pending_payment' && $latest === null => 'unpaid',
            $order->status === 'pending_payment' => 'pending',
            default => 'other',
        };
    }

    public function confirmation(Request $request, string $orderNumber, PaymentService $payments)
    {
        $order = Order::query()->where('order_number', $orderNumber)->with(['items', 'customer', 'user'])->first();

        // Only the owner (signed in) or the browser session that placed the order may see it; otherwise it does not exist.
        abort_unless($order && GuestOrderAccess::can($request, $order), 404);

        $this->refreshPendingPayment($order, $payments);

        $latest = $order->paymentAttempts()->latest('id')->first();
        $state = $this->confirmationState($order);

        $event = Event::query()->find($order->items->pluck('event_id')->filter()->first());
        $tickets = $state === 'paid' && $order->tickets_generated_at ? $order->issuedTickets()->orderBy('id')->get() : collect();

        $isOwnerSignedIn = $request->user() && (int) $request->user()->id === (int) $order->user_id && ! $order->owner_unverified;

        // Signed-in owners use their account pages; a browser that only holds the order gets short-lived signed PDF links.
        $downloads = [];
        if (! $isOwnerSignedIn) {
            foreach ($tickets as $ticket) {
                $downloads[$ticket->id] = URL::temporarySignedRoute('front.tickets.public-download', now()->addHour(), ['ticketNumber' => $ticket->ticket_number]);
            }
        }

        return view('front.checkout.confirmation', [
            'order' => $order, 'state' => $state, 'event' => $event, 'tickets' => $tickets, 'attempt' => $latest, 'step' => 3,
            'isOwnerSignedIn' => $isOwnerSignedIn,
            'downloads' => $downloads,
            'newAccount' => in_array($order->id, (array) session(self::NEW_ACCOUNT_ORDERS, []), true),
            'accountEmail' => $order->user?->email,
            'accountEmailMasked' => $order->user ? GuestOrderAccess::mask($order->user->email) : null,
            'passwordless' => $order->user?->passwordless_since !== null,
            'canSetPassword' => $isOwnerSignedIn
                && $order->user?->passwordless_since !== null
                && (int) session('auth.otp_verified_at', 0) >= now()->subMinutes(15)->timestamp,
        ]);
    }

    /* ------------------------------------------------------------------ helpers */

    /** @return array{0: Event, 1: array<string, mixed>}|null the event and a freshly re-validated selection, or null */
    private function context(): ?array
    {
        $stored = session(self::SELECTION);
        if (! is_array($stored) || empty($stored['units'])) {
            return null;
        }

        $event = Event::query()->with('fees')->find((int) ($stored['event_id'] ?? 0));
        if (! $event) {
            return null;
        }

        try {
            // Rebuilt from current data: closed events, sold-out tickets, new limits and new prices are all picked up here.
            $fresh = $this->selection->revalidate($event, collect($stored['units']));
        } catch (ValidationException $e) {
            $this->clearCheckoutSession();
            session()->flash('error', $this->firstError($e, 'Your ticket selection is no longer available. Please select again.'));
            session()->flash('checkout_event', $event->slug);

            return null;
        }

        session([self::SELECTION => $fresh]);

        return [$event, $fresh];
    }

    private function startSelection(array $selection): void
    {
        $previous = session(self::SELECTION);
        $sameShape = is_array($previous) && collect($previous['units'] ?? [])->pluck('ticket_id')->all() === collect($selection['units'])->pluck('ticket_id')->all();

        session([self::SELECTION => $selection, self::SELECTION_ID => (string) Str::uuid()]);
        session()->forget(self::PROMO);

        // Keep what the customer typed if the selection did not change; otherwise only the buyer block is reusable.
        $draft = session(self::DRAFT);
        if (! $sameShape && is_array($draft)) {
            session([self::DRAFT => ['buyer' => $draft['buyer'] ?? [], 'attendees' => [], 'payment_method' => $draft['payment_method'] ?? '']]);
        }
    }

    /** The methods THIS customer was offered (their reservation) — or the pre-existing list when routing is off / not needed. */
    private function methodOffered(Request $request, PaymentGatewayManager $gateways, string $code): bool
    {
        $svc = app(\App\Services\Payments\Routing\CheckoutRoutingService::class);
        if (! $svc->enabled()) {
            return $svc->methodsWithoutReservation()->contains('code', $code);
        }

        // Routing on: the reservation decides (inside place()). A method that is no longer offered, an expired or missing reservation
        // all end the same way: 409 "options changed", the customer chooses again, nothing is created, nothing is lost.
        return true;
    }

    private function clearCheckoutSession(): void
    {
        session()->forget([self::SELECTION, self::DRAFT, self::PROMO, self::SELECTION_ID, 'checkout_verify_email', 'checkout.preflight_email', \App\Services\Payments\Routing\CheckoutRoutingService::SESSION_KEY]);
    }

    private function backToEvents(string $message)
    {
        return redirect()->route('front.events')->with('error', $message);
    }

    private function invalidContextRedirect()
    {
        if ($slug = session('checkout_event')) {
            return redirect()->route('front.events.show', $slug);
        }

        return $this->backToEvents('Please choose an event and tickets first.');
    }

    private function respondInvalid(Request $request)
    {
        $message = (string) (session('error') ?: 'Please choose an event and tickets first.');
        $url = ($slug = session('checkout_event')) ? route('front.events.show', $slug) : route('front.events');

        if ($request->expectsJson()) {
            return response()->json(['ok' => false, 'message' => $message, 'redirect' => $url], 409);
        }

        return redirect($url)->with('error', $message);
    }

    private function respondWith(Request $request, string $url)
    {
        return $request->expectsJson() ? response()->json(['ok' => true, 'redirect' => $url]) : redirect()->to($url);
    }

    private function respondError(Request $request, string $message, int $status = 422)
    {
        return $request->expectsJson()
            ? response()->json(['ok' => false, 'message' => $message], $status)
            : back()->with('error', $message);
    }

    private function respondVerification(Request $request, string $email)
    {
        // Probing e-mails through this step is throttled per client; the wording never says "account exists".
        $key = 'checkout-verify:'.($request->ip() ?? 'x');
        if (RateLimiter::tooManyAttempts($key, 8)) {
            return $request->expectsJson()
                ? response()->json(['ok' => false, 'message' => 'Too many attempts. Please wait a while and try again.'], 429)
                : back()->withInput()->with('error', 'Too many attempts. Please wait a while and try again.');
        }
        RateLimiter::hit($key, 3600);

        if ($request->expectsJson()) {
            return response()->json([
                'ok' => false, 'needs_verification' => true, 'masked' => GuestOrderAccess::mask($email),
                'message' => 'To keep your booking safe, confirm this email with a code. Your booking details are kept.',
            ], 409);
        }

        return back()->withInput()->with('checkout_verify_email', $email);
    }

    private function handlePlacementError(Request $request, ValidationException $e, Event $event)
    {
        $errors = $e->errors();

        if (isset($errors['price_changed'])) {
            $message = $this->firstError($e, 'The price changed. Please review your order.');
            $routingChanged = $e instanceof \App\Services\Payments\Routing\ReservationInvalid;
            if ($routingChanged) {
                // The draft (buyer + attendees) is already stored in the session: reloading re-renders it with a fresh reservation.
                Log::info('Checkout routing reservation invalid.', ['reason' => $e->reason]);
                session()->flash('error', $message); // shown again after the page reloads (the AJAX alert disappears with it)
            }

            return $request->expectsJson()
                ? response()->json(['ok' => false, 'price_changed' => true, 'routing_changed' => $routingChanged, 'message' => $message], 409)
                : redirect()->route('front.checkout')->with('error', $message);
        }
        if (isset($errors['cart']) || isset($errors['tickets'])) {
            $url = route('front.events.show', $event);
            session()->flash('error', $this->firstError($e, 'Your selection is no longer available.'));

            return $request->expectsJson()
                ? response()->json(['ok' => false, 'message' => $this->firstError($e, 'Your selection is no longer available.'), 'redirect' => $url], 409)
                : redirect($url);
        }
        if (isset($errors['promo_code'])) {
            session()->forget(self::PROMO);
        }

        throw $e;
    }

    private function afterPlacementUrl(Order $order, PaymentGatewayManager $gateways): string
    {
        if ($order->status === 'pending_payment' && $order->payment_link_token) {
            $method = PaymentMethod::query()->where('code', $order->payment_method)->first();
            if ($method && $gateways->isOnline($method)) {
                return route('front.orders.payment.gateway', ['order' => $order, 'token' => $order->payment_link_token, 'method' => $method->code]);
            }
        }

        return route('front.checkout.confirmation', $order->order_number);
    }

    /** Order placed by THIS browser session in the last few minutes (makes re-submits and the back button harmless). */
    private function recentOrder(Request $request): ?Order
    {
        $number = session('checkout.last_order');
        $order = $number ? Order::query()->where('order_number', $number)->where('created_at', '>=', now()->subMinutes(15))->first() : null;

        return $order && GuestOrderAccess::can($request, $order) ? $order : null;
    }

    private function recentUnpaidOrder(Request $request): ?Order
    {
        $order = $this->recentOrder($request);

        return $order && $order->status === 'pending_payment' && $order->payment_link_token ? $order : null;
    }

    private function findDoneOrder(Request $request, string $key): ?Order
    {
        $number = Cache::get($key);
        $order = $number ? Order::query()->where('order_number', $number)->first() : null;

        return $order && GuestOrderAccess::can($request, $order) ? $order : null;
    }

    /** Only the known fields, as short strings: what the customer typed, kept for a refresh (never validated here). */
    private function attendeeInput(Request $request): array
    {
        $rows = [];
        foreach (array_slice(array_values((array) $request->input('attendees', [])), 0, 50) as $row) {
            $row = (array) $row;
            $rows[] = collect(['name', 'email', 'phone', 'gender', 'social_profile'])
                ->mapWithKeys(fn ($key) => [$key => mb_substr(trim((string) ($row[$key] ?? '')), 0, 255)])->all();
        }

        return $rows;
    }

    /**
     * Wizard step 1 → 2. Validates the buyer block with the same rules as the final submit and tells the page whether
     * this e-mail needs the code box now. It creates nothing: no account, no order (those only happen at payment).
     */
    public function preflight(Request $request, CheckoutIdentity $identity)
    {
        if (! $this->context()) {
            return $this->respondInvalid($request);
        }

        $buyer = $this->validateBuyer($request);

        $draft = (array) session(self::DRAFT, []);
        session([self::DRAFT => array_merge(['attendees' => [], 'payment_method' => ''], $draft, ['buyer' => $this->buyerInput($request)])]);

        try {
            $identity->inspect($request, $buyer);
        } catch (EmailVerificationRequired $e) {
            session()->forget('checkout.preflight_email');

            return $this->respondVerification($request, $e->email);
        } catch (StaffEmailNotAllowed $e) {
            throw ValidationException::withMessages(['email' => $e->getMessage()]);
        }

        session(['checkout.preflight_email' => \App\Services\Auth\EmailOtpService::normalize($buyer['email'])]);

        return response()->json(['ok' => true]);
    }

    /** Wizard: keeps what has been typed so far (session only) so a refresh restores it. Nothing is validated or created. */
    public function draft(Request $request)
    {
        if (! $this->context()) {
            return response()->json(['ok' => false], 409);
        }

        session([self::DRAFT => [
            'buyer' => $this->buyerInput($request),
            'attendees' => $this->attendeeInput($request),
            'payment_method' => mb_substr((string) $request->input('payment_method', ''), 0, 80),
        ]]);

        return response()->json(['ok' => true]);
    }

    /** Which checkout experience to render; outside production ?experience=wizard|one-page switches it for comparison. */
    private function experience(Request $request): string
    {
        $value = (string) config('checkout.experience');

        if (! app()->isProduction()) {
            if (in_array($request->query('experience'), ['wizard', 'one-page'], true)) {
                session(['checkout.experience' => $request->query('experience')]);
            }
            if (in_array(session('checkout.experience'), ['wizard', 'one-page'], true)) {
                $value = session('checkout.experience');
            }
        }

        return $value;
    }

    /** @param \Illuminate\Support\Collection<int, array<string, mixed>> $units */
    private function groups($units): array
    {
        $groups = [];
        $index = 0;
        foreach ($units as $unit) {
            $key = (int) $unit['ticket_id'];
            $groups[$key] ??= ['name' => (string) $unit['ticket_name'], 'price' => (float) $unit['ticket_price'], 'is_couple' => (bool) ($unit['is_couple'] ?? false), 'indexes' => []];
            $groups[$key]['indexes'][] = $index++;
        }

        return array_values($groups);
    }

    private function notBlockedEmailDomain(): \Closure
    {
        return function (string $attribute, mixed $value, \Closure $fail) {
            if (self::isBlockedEmailDomain((string) $value)) {
                $fail('Please use a real email address.');
            }
        };
    }

    private static function isBlockedEmailDomain(string $email): bool
    {
        $domain = strtolower(substr(strrchr(trim($email), '@') ?: '', 1));

        foreach ((array) config('checkout.blocked_email_domains', []) as $blocked) {
            $blocked = strtolower($blocked);
            if ($domain === $blocked || str_ends_with($domain, '.'.$blocked)) {
                return true;
            }
        }

        return false;
    }

    /** Names: letters (any script), marks, spaces and . ' - only; rejects markup/script payloads and digits. */
    private const NAME_PATTERN = "/^[\\p{L}\\p{M}\\s.'’\\-]+$/u";

    /** Phones: digits with the usual + - ( ) and space separators. */
    private const PHONE_PATTERN = '/^[0-9+\\-()\\s]+$/';

    /** The buyer block as typed (not validated): used to keep the draft. */
    private function buyerInput(Request $request): array
    {
        $full = trim((string) $request->input('full_name', trim($request->input('first_name', '').' '.$request->input('last_name', ''))));

        return ['full_name' => $full, 'email' => trim((string) $request->input('email', '')), 'phone' => trim((string) $request->input('phone', ''))];
    }

    /** @return array{first_name: string, last_name: string, email: string, phone: ?string} */
    private function validateBuyer(Request $request): array
    {
        if ($request->filled('full_name') && ! $request->filled('first_name')) {
            $parts = preg_split('/\s+/', trim((string) $request->input('full_name')), 2) ?: [];
            $request->merge(['first_name' => $parts[0] ?? '', 'last_name' => $parts[1] ?? '-']);
        }

        $data = $request->validate([
            'first_name' => ['required', 'string', 'max:255', 'regex:'.self::NAME_PATTERN],
            'last_name' => ['required', 'string', 'max:255', 'regex:'.self::NAME_PATTERN],
            'email' => ['required', 'email', 'max:255', $this->notBlockedEmailDomain()],
            'phone' => ['nullable', 'string', 'max:255', 'regex:'.self::PHONE_PATTERN],
        ], [
            'first_name.regex' => 'Please enter a valid name (letters only).',
            'last_name.regex' => 'Please enter a valid name (letters only).',
            'phone.regex' => 'Please enter a valid phone number.',
            'first_name.required' => 'Please enter your full name.',
            'last_name.required' => 'Please enter your full name.',
            'email.required' => 'Please enter your email address.',
        ]);

        return [
            'first_name' => trim($data['first_name']),
            'last_name' => trim($data['last_name']),
            'email' => trim($data['email']),
            'phone' => isset($data['phone']) ? trim($data['phone']) : null,
        ];
    }

    /**
     * Required attendee fields exactly as the system has always required them.
     *
     * @return array<int, array<string, string>>
     */
    private function validateAttendees(array $attendees, int $count): array
    {
        $attendees = array_values($attendees);

        if (count($attendees) !== $count) {
            throw ValidationException::withMessages(['attendees' => 'Please fill attendee data for all selected tickets.']);
        }

        $errors = [];
        $clean = [];
        foreach ($attendees as $index => $attendee) {
            $attendee = (array) $attendee;
            $row = [
                'name' => trim((string) ($attendee['name'] ?? '')),
                'phone' => trim((string) ($attendee['phone'] ?? '')),
                'email' => trim((string) ($attendee['email'] ?? '')),
                'gender' => trim((string) ($attendee['gender'] ?? '')),
                'social_profile' => trim((string) ($attendee['social_profile'] ?? '')),
            ];

            if ($row['name'] === '') {
                $errors["attendees.$index.name"] = 'Name is required.';
            } elseif (! preg_match(self::NAME_PATTERN, $row['name'])) {
                $errors["attendees.$index.name"] = 'Please enter a valid name (letters only).';
            }
            if ($row['phone'] === '') {
                $errors["attendees.$index.phone"] = 'Phone is required.';
            } elseif (! preg_match(self::PHONE_PATTERN, $row['phone'])) {
                $errors["attendees.$index.phone"] = 'Please enter a valid phone number.';
            }
            if (! filter_var($row['email'], FILTER_VALIDATE_EMAIL) || self::isBlockedEmailDomain($row['email'])) {
                $errors["attendees.$index.email"] = 'Valid email is required.';
            }
            if (! in_array($row['gender'], ['male', 'female'], true)) {
                $errors["attendees.$index.gender"] = 'Gender is required.';
            }
            if ($row['social_profile'] === '') {
                $errors["attendees.$index.social_profile"] = 'Social profile is required.';
            }

            $clean[] = $row;
        }

        if ($errors) {
            throw ValidationException::withMessages($errors);
        }

        return $clean;
    }

    private function affiliateUserId(Request $request, $user): ?int
    {
        $fromSession = (int) $request->session()->get('affiliate.referrer_id', 0);
        if ($fromSession > 0) {
            return $fromSession;
        }

        $fallback = (int) ($user->referred_by_user_id ?? 0);

        return $fallback > 0 ? $fallback : null;
    }

    private function buyerDefaults($user): array
    {
        $customer = Customer::query()->where('email', $user->email)->first();
        $parts = preg_split('/\s+/', trim((string) $user->name), 2) ?: [];

        $first = trim((string) ($parts[0] ?? ''));
        $last = trim((string) ($parts[1] ?? ''));
        $phone = trim((string) ($user->phone ?? ''));
        $name = trim(($first !== '' ? $first : ($customer?->first_name ?? '')).' '.($last !== '' ? $last : ($customer?->last_name ?? '')));

        return [
            'full_name' => $name,
            'email' => $user->email,
            'phone' => $phone !== '' ? $phone : ($customer?->phone ?? ''),
        ];
    }

    private function firstError(ValidationException $e, string $fallback): string
    {
        return (string) (collect($e->errors())->flatten()->first() ?: $fallback);
    }
}
