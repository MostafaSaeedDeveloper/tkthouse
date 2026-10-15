<?php

namespace App\Http\Controllers;

use App\Models\Customer;
use App\Models\Event;
use App\Models\Order;
use App\Services\Checkout\CheckoutPricing;
use App\Services\Checkout\CheckoutSelection;
use App\Services\Checkout\OrderPlacementService;
use App\Services\Payments\PaymentGatewayManager;
use App\Services\Payments\PaymentService;
use Illuminate\Contracts\Cache\LockTimeoutException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * Checkout V2.  Step 1 (tickets) lives on the event page; this controller runs
 *   Step 2  details   GET /checkout            + POST /checkout/details
 *   Step 3  review    GET /checkout/review     + POST /checkout/promo, POST /checkout (place order)
 *   Step 4  result    GET /checkout/confirmation/{order}
 * The selection, prices, fees and promo are always recomputed on the server; the session only remembers
 * which tickets were chosen and what the customer typed.
 */
class CheckoutController extends Controller
{
    private const SELECTION = 'checkout.event_selection';
    private const DETAILS = 'checkout.details';
    private const PROMO = 'checkout.promo';
    private const SELECTION_ID = 'checkout.selection_id';

    public function __construct(
        private readonly CheckoutSelection $selection,
        private readonly CheckoutPricing $pricing,
    ) {
    }

    /* ------------------------------------------------------------------ Step 2: details */

    public function index(Request $request)
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

            return redirect()->route('front.checkout'); // clean URL; refresh and back stay on the same step
        }

        if (! $context = $this->context($request)) {
            return $this->invalidContextRedirect();
        }

        [$event, $selection] = $context;
        $units = collect($selection['units']);

        $details = session(self::DETAILS, []);
        $buyer = array_merge($this->buyerDefaults($request), array_filter((array) ($details['buyer'] ?? []), fn ($v) => $v !== null && $v !== ''));

        return view('front.checkout.details', [
            'event' => $event,
            'groups' => $this->groups($units),
            'buyer' => $buyer,
            'attendees' => (array) ($details['attendees'] ?? []),
            'quote' => $this->pricing->quote($event, $units),
            'step' => 2,
        ]);
    }

    public function saveDetails(Request $request)
    {
        if (! $context = $this->context($request)) {
            return $this->invalidContextRedirect();
        }

        $units = collect($context[1]['units']);

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
        if ($errors) {
            throw ValidationException::withMessages($errors);
        }

        session([self::DETAILS => ['buyer' => $buyer, 'attendees' => $attendees]]);

        return redirect()->route('front.checkout.review');
    }

    /* ------------------------------------------------------------------ Step 3: review & payment */

    public function review(Request $request, PaymentGatewayManager $gateways)
    {
        if (! $context = $this->context($request)) {
            return $this->invalidContextRedirect();
        }

        [$event, $selection] = $context;
        $details = session(self::DETAILS);

        if (! $this->detailsMatch($details, count($selection['units']))) {
            return redirect()->route('front.checkout')->with('error', 'Please complete your details first.');
        }

        $units = collect($selection['units']);
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

        return view('front.checkout.review', [
            'event' => $event,
            'quote' => $quote,
            'groups' => $this->groups($units),
            'buyer' => $details['buyer'],
            'attendees' => $details['attendees'],
            'methods' => $gateways->available(),
            'requiresApproval' => (bool) $event->requires_booking_approval,
            'promoCode' => $promoCode,
            'promoNotice' => $promoNotice,
            'step' => 3,
        ]);
    }

    /** Previews a promo code. The discount is computed here, never in the browser. */
    public function applyPromo(Request $request)
    {
        if (! $context = $this->context($request)) {
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
                : redirect()->route('front.checkout.review');
        }

        $code = strtoupper(trim((string) $request->input('promo_code')));

        try {
            $quote = $this->pricing->quote($event, $units, $code);
        } catch (ValidationException $e) {
            $message = $this->firstError($e, 'Promo code is invalid.');

            return $request->expectsJson()
                ? response()->json(['ok' => false, 'message' => $message], 422)
                : redirect()->route('front.checkout.review')->withErrors(['promo_code' => $message])->withInput();
        }

        session([self::PROMO => $code]);

        return $request->expectsJson()
            ? response()->json(['ok' => true, 'message' => "Promo code {$code} applied.", 'quote' => $quote->toArray()])
            : redirect()->route('front.checkout.review');
    }

    /** Places the order. Safe against double clicks, refreshes and parallel submissions. */
    public function store(Request $request, PaymentGatewayManager $gateways, OrderPlacementService $placement)
    {
        if (! $context = $this->context($request)) {
            // Back button / second tab right after a successful submit: take the customer to the order just created.
            if ($recent = $this->recentOrder($request)) {
                return $this->afterPlacement($recent, $gateways);
            }

            return $this->invalidContextRedirect();
        }

        [$event, $selection] = $context;
        $user = $request->user();
        $selectionId = (string) session(self::SELECTION_ID, '');
        $doneKey = "checkout-done:{$user->id}:{$selectionId}";

        // Already placed from this very selection (double click, back button, second tab): go to that order.
        if ($selectionId !== '' && ($existing = $this->findDoneOrder($user->id, $doneKey))) {
            return $this->afterPlacement($existing, $gateways);
        }

        $lock = Cache::lock("checkout-lock:{$user->id}", 20);
        try {
            $lock->block(8);
        } catch (LockTimeoutException) {
            return back()->with('error', 'Your order is already being processed. Please wait a moment.');
        }

        try {
            if ($selectionId !== '' && ($existing = $this->findDoneOrder($user->id, $doneKey))) {
                return $this->afterPlacement($existing, $gateways);
            }

            $details = $this->resolveDetails($request, count($selection['units']));
            $requiresApproval = (bool) $event->requires_booking_approval;

            $methodCode = null;
            if (! $requiresApproval) {
                $methodCode = (string) $request->validate([
                    'payment_method' => ['required', 'string'],
                ], ['payment_method.required' => 'Please choose a payment method.'])['payment_method'];

                if (! $gateways->available()->contains('code', $methodCode)) {
                    throw ValidationException::withMessages(['payment_method' => 'The selected payment method is not available.']);
                }
            }

            $promo = trim((string) $request->input('promo_code', session(self::PROMO, '')));
            $expected = $request->filled('expected_total') ? (float) $request->input('expected_total') : null;

            try {
                $order = $placement->place(
                    $user, $event, collect($selection['units']), $details['buyer'], $details['attendees'],
                    $promo !== '' ? $promo : null, $methodCode, $expected, $this->affiliateUserId($request)
                );
            } catch (ValidationException $e) {
                if (isset($e->errors()['price_changed'])) {
                    return redirect()->route('front.checkout.review')->with('error', $this->firstError($e, 'Prices changed. Please review your order.'));
                }
                if (isset($e->errors()['cart']) || isset($e->errors()['tickets'])) {
                    return redirect()->route('front.events.show', $event)->with('error', $this->firstError($e, 'Your selection is no longer available.'));
                }
                if (isset($e->errors()['promo_code'])) {
                    session()->forget(self::PROMO);
                    return redirect()->route('front.checkout.review')->withErrors(['promo_code' => $this->firstError($e, 'Promo code is invalid.')]);
                }

                throw $e;
            }

            if ($selectionId !== '') {
                Cache::put($doneKey, $order->order_number, now()->addHour());
            }
            $this->clearCheckoutSession();
            session(['checkout.last_order' => $order->order_number]);

            return $this->afterPlacement($order, $gateways);
        } finally {
            $lock->release();
        }
    }

    /* ------------------------------------------------------------------ Step 4: confirmation */

    /** Legacy URL (older emails / gateway return links): resolve to the real, status-driven confirmation page. */
    public function thankYou(Request $request)
    {
        $orderNumber = trim((string) $request->query('order'));

        if ($orderNumber !== '' && Order::query()->where('order_number', $orderNumber)->where('user_id', $request->user()->id)->exists()) {
            return redirect()->route('front.checkout.confirmation', $orderNumber);
        }

        return redirect()->route('front.account.orders');
    }

    /** What is shown comes from the order's real status, never from a query string, so a URL can not fake a payment. */
    public function confirmation(Request $request, string $orderNumber, PaymentService $payments)
    {
        $order = Order::query()
            ->where('order_number', $orderNumber)
            ->where('user_id', $request->user()->id)
            ->with(['items', 'customer'])
            ->firstOrFail();

        if ($order->status === 'pending_payment' && $order->paymentAttempts()->exists() && Cache::add('order-refresh:'.$order->id, 1, 5)) {
            // The customer may be back before the gateway callback: ask the gateway instead of guessing.
            try {
                $payments->refresh($order);
            } catch (\Throwable $e) {
                Log::warning('Confirmation refresh failed.', ['order_id' => $order->id, 'error' => $e->getMessage()]);
            }
            $order->refresh();
        }

        $latest = $order->paymentAttempts()->latest('id')->first();
        $hasOpen = $order->paymentAttempts()->where('status', 'initiated')->exists();

        $state = match (true) {
            $order->status === 'paid' => 'paid',
            $order->status === 'pending_approval' => 'review',
            in_array($order->status, ['canceled', 'rejected'], true) => 'closed',
            $order->status === 'pending_payment' && $latest?->status === 'failed' && ! $hasOpen => 'failed',
            $order->status === 'pending_payment' && $latest === null => 'unpaid',
            $order->status === 'pending_payment' => 'pending',
            default => 'other',
        };

        $event = Event::query()->find($order->items->pluck('event_id')->filter()->first());
        $tickets = $state === 'paid' && $order->tickets_generated_at ? $order->issuedTickets()->orderBy('id')->get() : collect();

        return view('front.checkout.confirmation', [
            'order' => $order, 'state' => $state, 'event' => $event, 'tickets' => $tickets, 'attempt' => $latest, 'step' => 4,
        ]);
    }

    /* ------------------------------------------------------------------ helpers */

    /** @return array{0: Event, 1: array<string, mixed>}|null the event and a freshly re-validated selection, or null */
    private function context(Request $request): ?array
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

    private function invalidContextRedirect()
    {
        if ($slug = session('checkout_event')) {
            return redirect()->route('front.events.show', $slug);
        }

        return $this->backToEvents('Please choose an event and tickets first.');
    }

    private function backToEvents(string $message)
    {
        return redirect()->route('front.events')->with('error', $message);
    }

    private function startSelection(array $selection): void
    {
        $previous = session(self::SELECTION);
        $sameShape = is_array($previous) && collect($previous['units'] ?? [])->pluck('ticket_id')->all() === collect($selection['units'])->pluck('ticket_id')->all();

        session([self::SELECTION => $selection, self::SELECTION_ID => (string) Str::uuid()]);
        session()->forget(self::PROMO);

        // Keep what the customer typed if the selection did not change; otherwise only the buyer block is reusable.
        $details = session(self::DETAILS);
        if (! $sameShape && is_array($details)) {
            session([self::DETAILS => ['buyer' => $details['buyer'] ?? [], 'attendees' => []]]);
        }
    }

    private function clearCheckoutSession(): void
    {
        session()->forget([self::SELECTION, self::DETAILS, self::PROMO, self::SELECTION_ID]);
    }

    /** The order this customer placed in the last few minutes, if any (used to make re-submits harmless). */
    private function recentOrder(Request $request): ?Order
    {
        $number = session('checkout.last_order');

        return $number
            ? Order::query()->where('order_number', $number)->where('user_id', $request->user()->id)->where('created_at', '>=', now()->subMinutes(15))->first()
            : null;
    }

    private function findDoneOrder(int $userId, string $key): ?Order
    {
        $number = Cache::get($key);

        return $number ? Order::query()->where('order_number', $number)->where('user_id', $userId)->first() : null;
    }

    private function afterPlacement(Order $order, PaymentGatewayManager $gateways)
    {
        if ($order->status === 'pending_payment' && $order->payment_link_token) {
            $method = \App\Models\PaymentMethod::query()->where('code', $order->payment_method)->first();
            if ($method && $gateways->isOnline($method)) {
                return redirect()->route('front.orders.payment.gateway', ['order' => $order, 'token' => $order->payment_link_token, 'method' => $method->code]);
            }
        }

        return redirect()->route('front.checkout.confirmation', $order->order_number);
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

    private function detailsMatch($details, int $count): bool
    {
        return is_array($details) && count((array) ($details['attendees'] ?? [])) === $count && ! empty($details['buyer']['email']);
    }

    /** Details from the form being posted (single-step clients) or, by default, from Step 2. */
    private function resolveDetails(Request $request, int $count): array
    {
        if ($request->has('attendees')) {
            return [
                'buyer' => $this->validateBuyer($request),
                'attendees' => $this->validateAttendees((array) $request->input('attendees'), $count),
            ];
        }

        $details = session(self::DETAILS);
        if (! $this->detailsMatch($details, $count)) {
            throw ValidationException::withMessages(['attendees' => 'Please fill attendee data for all selected tickets.']);
        }

        return $details;
    }

    /** @return array{first_name: string, last_name: string, email: string, phone: ?string} */
    private function validateBuyer(Request $request): array
    {
        if ($request->filled('full_name') && ! $request->filled('first_name')) {
            $parts = preg_split('/\s+/', trim((string) $request->input('full_name')), 2) ?: [];
            $request->merge(['first_name' => $parts[0] ?? '', 'last_name' => $parts[1] ?? '-']);
        }

        $data = $request->validate([
            'first_name' => ['required', 'string', 'max:255'],
            'last_name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255'],
            'phone' => ['nullable', 'string', 'max:255'],
        ], [
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
            }
            if ($row['phone'] === '') {
                $errors["attendees.$index.phone"] = 'Phone is required.';
            }
            if (! filter_var($row['email'], FILTER_VALIDATE_EMAIL)) {
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

    private function affiliateUserId(Request $request): ?int
    {
        $fromSession = (int) $request->session()->get('affiliate.referrer_id', 0);
        if ($fromSession > 0) {
            return $fromSession;
        }

        $fallback = (int) ($request->user()->referred_by_user_id ?? 0);

        return $fallback > 0 ? $fallback : null;
    }

    private function buyerDefaults(Request $request): array
    {
        $user = $request->user();
        $customer = Customer::query()->where('email', $user->email)->first();
        $parts = preg_split('/\s+/', trim((string) $user->name), 2) ?: [];

        $first = trim((string) ($parts[0] ?? ''));
        $last = trim((string) ($parts[1] ?? ''));
        $phone = trim((string) ($user->phone ?? ''));

        return [
            'first_name' => $first !== '' ? $first : ($customer?->first_name ?? ''),
            'last_name' => $last !== '' ? $last : ($customer?->last_name ?? ''),
            'email' => $user->email,
            'phone' => $phone !== '' ? $phone : ($customer?->phone ?? ''),
        ];
    }

    private function firstError(ValidationException $e, string $fallback): string
    {
        return (string) (collect($e->errors())->flatten()->first() ?: $fallback);
    }
}
