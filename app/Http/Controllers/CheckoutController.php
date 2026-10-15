<?php

namespace App\Http\Controllers;

use App\Models\Customer;
use App\Models\Event;
use App\Models\EventTicket;
use App\Models\Order;
use App\Models\PaymentMethod;
use App\Models\PromoCode;
use App\Services\Payments\PaymentGatewayManager;
use App\Services\Payments\PaymentService;
use App\Services\PendingPaymentExpiryService;
use App\Services\PaymobService;
use App\Services\TicketIssuanceService;
use App\Support\SystemSettings;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\Rule;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Spatie\Activitylog\Models\Activity;

class CheckoutController extends Controller
{
    public function index(Request $request)
    {
        // Checkout always belongs to one valid event: either a fresh cart from the event page or a still-valid session selection.
        if ($request->filled('event') || $request->filled('cart')) {
            if (! $request->filled('event') || ! $request->filled('cart')) {
                return $this->backToEvents('Please choose an event and tickets first.');
            }

            $event = Event::query()->findOrFail((int) $request->input('event'));

            try {
                $selection = $this->normalizeEventCart($event, (string) $request->input('cart'));
            } catch (ValidationException $e) {
                return redirect()->route('front.events.show', $event)
                    ->with('error', collect($e->errors())->flatten()->first() ?: 'Your ticket selection is not valid.');
            }

            session(['checkout.event_selection' => $selection]);
        } elseif ($stored = session('checkout.event_selection')) {
            $event = Event::query()->find((int) ($stored['event_id'] ?? 0));

            try {
                abort_unless($event, 404);
                $this->assertSelectionStillValid($event, collect($stored['units'] ?? []));
            } catch (ValidationException|\Symfony\Component\HttpKernel\Exception\NotFoundHttpException $e) {
                session()->forget('checkout.event_selection');

                return $event
                    ? redirect()->route('front.events.show', $event)->with('error', 'Your ticket selection is no longer available. Please select again.')
                    : $this->backToEvents('Please choose an event and tickets first.');
            }
        }

        $eventSelection = $this->hydrateEventSelectionWithFees(session('checkout.event_selection'));

        if (! $eventSelection) {
            return $this->backToEvents('Please choose an event and tickets first.');
        }

        return view('front.checkout', [
            'mode' => 'event_locked',
            'eventSelection' => $eventSelection,
            'buyer' => $this->buyerDefaults($request),
            'promoCodesPreview' => $this->promoCodesPreview(),
            'activePaymentMethods' => PaymentMethod::query()->where('is_active', true)->where('code', '!=', 'card')->orderBy('id')->get(['name', 'code', 'checkout_label', 'checkout_icon', 'checkout_description']),
        ]);
    }

    private function backToEvents(string $message)
    {
        return redirect()->route('front.events')->with('error', $message);
    }

    public function store(Request $request)
    {
        $baseValidated = $request->validate([
            'first_name' => ['required', 'string', 'max:255'],
            'last_name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255'],
            'phone' => ['nullable', 'string', 'max:255'],
            'promo_code' => ['nullable', 'string', 'max:50'],
        ]);

        if (! session('checkout.event_selection')) {
            return $this->backToEvents('Please choose an event and tickets first.');
        }

        return $this->storeFromLockedEventSelection($request, $baseValidated);
    }

    /** Legacy URL (older emails / gateway return links): resolve to the real, status-driven confirmation page. */
    public function thankYou(Request $request)
    {
        $orderNumber = trim((string) $request->query('order'));

        if ($orderNumber !== '' && Order::query()->where('order_number', $orderNumber)->where('user_id', $request->user()->id)->exists()) {
            return redirect()->route('front.checkout.confirmation', $orderNumber);
        }

        return redirect()->route('front.account.orders');
    }

    /**
     * Step 4. What is shown comes from the order's real status, never from a query string, so a URL can not
     * fake a successful payment.
     */
    public function confirmation(Request $request, string $orderNumber, PaymentService $payments)
    {
        $order = Order::query()
            ->where('order_number', $orderNumber)
            ->where('user_id', $request->user()->id)
            ->with(['items', 'customer'])
            ->firstOrFail();

        if ($order->status === 'pending_payment' && $order->paymentAttempts()->exists()
            && Cache::add('order-refresh:'.$order->id, 1, 5)) {
            // Customer may be back before the gateway callback: ask the gateway instead of guessing.
            try {
                $payments->refresh($order);
            } catch (\Throwable $e) {
                Log::warning('Confirmation refresh failed.', ['order_id' => $order->id, 'error' => $e->getMessage()]);
            }
            $order->refresh();
        }

        $latestAttempt = $order->paymentAttempts()->latest('id')->first();

        $state = match (true) {
            $order->status === 'paid' => 'paid',
            $order->status === 'pending_approval' => 'review',
            in_array($order->status, ['canceled', 'rejected'], true) => 'closed',
            $order->status === 'pending_payment' && $latestAttempt?->status === 'failed' && ! $order->paymentAttempts()->where('status', 'initiated')->exists() => 'failed',
            $order->status === 'pending_payment' && $latestAttempt === null => 'unpaid',
            default => $order->status === 'pending_payment' ? 'pending' : 'other',
        };

        $event = Event::query()->find($order->items->pluck('event_id')->filter()->first());
        $tickets = $state === 'paid' && $order->tickets_generated_at
            ? $order->issuedTickets()->orderBy('id')->get()
            : collect();

        return view('front.checkout.confirmation', [
            'order' => $order,
            'state' => $state,
            'event' => $event,
            'tickets' => $tickets,
            'attempt' => $latestAttempt,
        ]);
    }

    public function paymentPage(Request $request, Order $order, string $token)
    {
        if ($this->expirePendingPaymentOrderIfNeeded($order)) {
            return redirect()->route('front.account.orders')->with('error', 'This order was canceled because the payment deadline has expired.');
        }

        abort_unless($request->user() && (int) $order->user_id === (int) $request->user()->id, 403);
        abort_unless($order->payment_link_token && hash_equals($order->payment_link_token, $token), 404);
        abort_unless($order->status === 'pending_payment', 404);

        $order->load(['items', 'customer']);

        $activePaymentMethods = $this->availablePaymentMethods();

        $selectedMethod = $activePaymentMethods->firstWhere('code', $order->payment_method)?->code
            ?? $activePaymentMethods->first()?->code
            ?? $order->payment_method;

        return view('front.payment', [
            'order' => $order,
            'activePaymentMethods' => $activePaymentMethods,
            'selectedMethod' => $selectedMethod,
        ]);
    }

    public function gatewayRedirect(Request $request, Order $order, string $token, PaymentService $payments, PaymentGatewayManager $gateways)
    {
        if ($this->expirePendingPaymentOrderIfNeeded($order)) {
            return redirect()->route('front.account.orders')->with('error', 'This order was canceled because the payment deadline has expired.');
        }

        abort_unless($request->user() && (int) $order->user_id === (int) $request->user()->id, 403);
        abort_unless($order->payment_link_token && hash_equals($order->payment_link_token, $token), 404);

        if ($order->status === 'paid') {
            return redirect()->route('front.checkout.confirmation', $order->order_number);
        }

        abort_unless($order->status === 'pending_payment', 404);

        $method = (string) $request->query('method', $order->payment_method);
        $paymentMethod = $this->availablePaymentMethods()->firstWhere('code', $method);

        if (! $paymentMethod || ! $gateways->supports((string) $paymentMethod->provider)) {
            return redirect()->route('front.orders.payment', ['order' => $order, 'token' => $token])
                ->withErrors(['payment' => 'Selected payment method is not available for online payment.']);
        }

        if ($order->payment_method !== $method) {
            $order->update(['payment_method' => $method]);
        }

        try {
            $url = $payments->start($order, $paymentMethod);

            $this->logPaymentEvent($order, $request->user(), 'Payment gateway opened', [
                'provider' => $paymentMethod->provider,
                'payment_method' => $paymentMethod->code,
                'payment_status' => 'opened',
                'transaction_url' => $url,
            ]);
        } catch (\Throwable $exception) {
            $this->logPaymentEvent($order, $request->user(), 'Payment attempt failed', [
                'provider' => $paymentMethod->provider,
                'payment_method' => $paymentMethod->code,
                'payment_status' => 'failed',
                'failure_reason' => $exception->getMessage(),
            ]);

            Log::warning('Could not open payment gateway.', ['order_id' => $order->id, 'error' => $exception->getMessage()]);

            return redirect()->route('front.orders.payment', ['order' => $order, 'token' => $token])
                ->withErrors(['payment' => 'We could not open the payment page right now. Please try again or choose another method.']);
        }

        return redirect()->away($url);
    }

    /**
     * Paymob return/processed callback. Whatever the body says, the order is only settled after the
     * transaction has been looked up at Paymob (see PaymentService::refresh).
     */
    public function paymobCallback(Request $request, PaymentService $payments)
    {
        $payload = $request->all();
        $merchantOrderId = (string) data_get($payload, 'obj.order.merchant_order_id', data_get($payload, 'merchant_order_id', ''));
        $transactionId = (string) data_get($payload, 'obj.id', data_get($payload, 'id', ''));
        $isGet = $request->isMethod('get');

        // Signed server-to-server callback: reject forged bodies early when the HMAC secret is configured.
        $secret = (string) (config('payments.paymob.hmac_secret') ?: '');
        if (! $isGet && $secret !== '' && is_array(data_get($payload, 'obj'))) {
            if (! PaymobService::hmacIsValid($payload['obj'], (string) $request->query('hmac', ''), $secret)) {
                Log::warning('Paymob callback rejected: invalid HMAC.', ['merchant_order_id' => $merchantOrderId]);

                return response()->json(['received' => false, 'updated' => false], 403);
            }
        }

        $order = $merchantOrderId !== '' ? $this->findOrderFromMerchantOrderId($merchantOrderId) : null;

        if (! $order) {
            Log::warning('Paymob callback order not found.', ['merchant_order_id' => $merchantOrderId]);

            return $isGet
                ? redirect()->route('front.checkout.thank-you', ['flow' => 'payment_failed'])
                : response()->json(['received' => true, 'updated' => false]);
        }

        $outcome = $payments->refresh($order, $transactionId !== '' ? $transactionId : null, 'paymob');

        Log::info('Paymob callback processed.', ['order_id' => $order->id, 'outcome' => $outcome]);

        if ($isGet) {
            return redirect()->route('front.checkout.confirmation', $order->order_number);
        }

        return response()->json(['received' => true, 'updated' => in_array($outcome, [PaymentService::OUTCOME_PAID, PaymentService::OUTCOME_ALREADY_PAID], true)]);
    }

    /**
     * Fawaterak redirect. The `result` query value is only a hint for the screen the customer lands on:
     * payment is confirmed by asking Fawaterak about the invoice, never by this URL.
     */
    public function fawaterakCallback(Request $request, Order $order, string $token, PaymentService $payments)
    {
        abort_unless($order->payment_link_token && hash_equals($order->payment_link_token, $token), 404);

        $hint = trim((string) ($request->query('invoice_id')
            ?: $request->query('payment_id')
            ?: $request->query('transaction_id')
            ?: $request->query('id')));

        $payments->refresh($order, $hint !== '' ? $hint : null, 'fawaterak');

        return redirect()->route('front.checkout.confirmation', $order->order_number);
    }

    private function logPaymentEvent(Order $order, $causer, string $description, array $properties = []): Activity
    {
        $logger = activity('orders')
            ->performedOn($order)
            ->withProperties($properties);

        if ($causer) {
            $logger->causedBy($causer);
        }

        return $logger->log($description);
    }

    private function storeFromLockedEventSelection(Request $request, array $baseValidated)
    {
        $selection = session('checkout.event_selection', []);
        $attendees = collect($request->input('attendees', []));
        $ticketUnits = collect($selection['units'] ?? []);

        if ($ticketUnits->isEmpty()) {
            throw ValidationException::withMessages(['tickets' => 'No selected tickets found. Please go back to event page and select tickets.']);
        }

        if ($attendees->count() !== $ticketUnits->count()) {
            throw ValidationException::withMessages(['attendees' => 'Please fill attendee data for all selected tickets.']);
        }

        $errors = [];
        foreach ($attendees as $index => $attendee) {
            $name = trim((string) ($attendee['name'] ?? ''));
            $phone = trim((string) ($attendee['phone'] ?? ''));
            $email = trim((string) ($attendee['email'] ?? ''));
            $gender = trim((string) ($attendee['gender'] ?? ''));
            $socialProfile = trim((string) ($attendee['social_profile'] ?? ''));

            if ($name === '') {
                $errors["attendees.$index.name"] = 'Name is required.';
            }
            if ($phone === '') {
                $errors["attendees.$index.phone"] = 'Phone is required.';
            }
            if (! filter_var($email, FILTER_VALIDATE_EMAIL)) {
                $errors["attendees.$index.email"] = 'Valid email is required.';
            }
            if (! in_array($gender, ['male', 'female'], true)) {
                $errors["attendees.$index.gender"] = 'Gender is required.';
            }
            if ($socialProfile === '') {
                $errors["attendees.$index.social_profile"] = 'Social profile is required.';
            }
        }

        if ($errors) {
            throw ValidationException::withMessages($errors);
        }

        $event = Event::query()->with('fees')->findOrFail((int) ($selection['event_id'] ?? 0));

        // The selection lives in the session; re-run every booking rule against current data when the order is placed.
        $this->assertSelectionStillValid($event, $ticketUnits);

        $requiresApproval = (bool) $event->requires_booking_approval;

        if (! $requiresApproval) {
            $request->validate([
                'payment_method' => ['required', Rule::in($this->activePaymentMethodCodes())],
            ]);
        }

        $subtotal = (float) $ticketUnits->sum(fn ($unit) => (float) $unit['ticket_price']);
        $feeSummary = $this->calculateEventFeeSummary($event, $subtotal);
        $feeTotal = (float) $feeSummary['total'];

        $order = DB::transaction(function () use ($request, $baseValidated, $ticketUnits, $attendees, $requiresApproval, $subtotal, $feeTotal, $event) {
            $promoData = $this->resolvePromoCodeForCheckout((string) $request->input('promo_code', ''), $subtotal);
            $customer = $this->upsertCustomer($baseValidated);

            $order = Order::create([
                'customer_id' => $customer->id,
                'user_id' => $request->user()->id,
                'affiliate_user_id' => $this->resolveAffiliateUserId($request),
                'promo_code_id' => $promoData['promo_code']?->id,
                'promo_code' => $promoData['promo_code']?->code,
                'discount_amount' => $promoData['discount_amount'],
                'subtotal_amount' => $subtotal,
                'order_number' => $this->generateNumericOrderNumber(),
                'status' => $requiresApproval ? 'pending_approval' : 'pending_payment',
                'requires_approval' => $requiresApproval,
                'payment_method' => $requiresApproval ? 'pending_review' : (string) $request->input('payment_method'),
                'payment_link_token' => $requiresApproval ? null : Str::random(40),
                'payment_timeout_started_at' => $requiresApproval ? null : now(),
                'payment_timeout_minutes' => $requiresApproval ? null : SystemSettings::pendingPaymentTimeoutMinutes(),
                'total_amount' => 0,
            ]);

            $total = 0;

            foreach ($ticketUnits as $index => $unit) {
                $attendee = $attendees[$index];
                $lineTotal = (float) $unit['ticket_price'];
                $total += $lineTotal;

                $order->items()->create([
                    'event_id' => $event->id,
                    'ticket_id' => null,
                    'ticket_name' => $unit['event_name'].' - '.$unit['ticket_name'],
                    'ticket_price' => $unit['ticket_price'],
                    'quantity' => 1,
                    'line_total' => $lineTotal,
                    'holder_name' => trim((string) ($attendee['name'] ?? '')),
                    'holder_email' => trim((string) ($attendee['email'] ?? '')),
                    'holder_phone' => trim((string) ($attendee['phone'] ?? '')),
                    'holder_gender' => (string) $attendee['gender'],
                    'holder_social_profile' => trim((string) ($attendee['social_profile'] ?? '')) ?: null,
                ]);
            }

            $discountedSubtotal = max(0, $total - $promoData['discount_amount']);
            $order->update(['total_amount' => $discountedSubtotal + $feeTotal]);

            activity('orders')
                ->performedOn($order)
                ->causedBy($request->user())
                ->withProperties([
                    'to_status' => $order->status,
                    'total_amount' => (float) $order->total_amount,
                ])
                ->log('Order submitted');

            return $order;
        });

        session()->forget('checkout.event_selection');

        if ($order->status === 'pending_payment') {
            return redirect()->route('front.account.orders')
                ->with('success', 'Order created successfully. Please complete payment before the deadline shown in your orders page.');
        }

        return redirect()->route('front.checkout.thank-you', ['flow' => 'pending_review', 'order' => $order->order_number])
            ->with('success', 'Your order has been submitted successfully.');
    }

    private function resolvePromoCodeForCheckout(string $rawPromoCode, float $subtotal): array
    {
        $code = strtoupper(trim($rawPromoCode));

        if ($code === '') {
            return ['promo_code' => null, 'discount_amount' => 0.0];
        }

        $promoCode = PromoCode::query()->where('code', $code)->lockForUpdate()->first();

        if (! $promoCode || ! $promoCode->is_active) {
            throw ValidationException::withMessages(['promo_code' => 'Promo code is invalid.']);
        }

        if ($promoCode->starts_at && now()->lt($promoCode->starts_at)) {
            throw ValidationException::withMessages(['promo_code' => 'Promo code is not active yet.']);
        }

        if ($promoCode->ends_at && now()->gt($promoCode->ends_at)) {
            throw ValidationException::withMessages(['promo_code' => 'Promo code has expired.']);
        }

        if ($promoCode->usage_limit !== null && $promoCode->used_count >= $promoCode->usage_limit) {
            throw ValidationException::withMessages(['promo_code' => 'Promo code usage limit reached.']);
        }

        $discountAmount = $promoCode->discount_type === 'percent'
            ? round(($subtotal * (float) $promoCode->discount_value) / 100, 2)
            : round((float) $promoCode->discount_value, 2);

        $discountAmount = min($discountAmount, $subtotal);

        $promoCode->increment('used_count');

        return ['promo_code' => $promoCode, 'discount_amount' => $discountAmount];
    }

    private function resolveAffiliateUserId(Request $request): ?int
    {
        $affiliateFromSession = (int) $request->session()->get('affiliate.referrer_id', 0);

        if ($affiliateFromSession > 0) {
            return $affiliateFromSession;
        }

        $fallback = (int) ($request->user()->referred_by_user_id ?? 0);

        return $fallback > 0 ? $fallback : null;
    }

    private function expirePendingPaymentOrderIfNeeded(Order $order): bool
    {
        if ($order->status !== 'pending_payment') {
            return false;
        }

        $order->loadMissing('customer');

        return app(PendingPaymentExpiryService::class)
            ->expireOrderIfNeeded($order, SystemSettings::pendingPaymentTimeoutMinutes());
    }



    private function findOrderFromMerchantOrderId(string $merchantOrderId): ?Order
    {
        $direct = Order::query()->where('order_number', $merchantOrderId)->first();
        if ($direct) {
            return $direct;
        }

        $baseOrderNumber = trim((string) strtok($merchantOrderId, '-'));
        if ($baseOrderNumber !== '') {
            return Order::query()->where('order_number', $baseOrderNumber)->first();
        }

        return null;
    }

    /** Payment methods a customer may use right now (active, not the legacy 'card', fake only when enabled). */
    private function availablePaymentMethods()
    {
        return PaymentMethod::query()
            ->where('is_active', true)
            ->where('code', '!=', 'card')
            ->orderBy('id')
            ->get()
            ->filter(fn (PaymentMethod $m) => $m->provider !== 'fake' || config('payments.fake.enabled'))
            ->values();
    }

    private function activePaymentMethodCodes(): array
    {
        return PaymentMethod::query()
            ->where('is_active', true)
            ->where('code', '!=', 'card')
            ->orderBy('id')
            ->pluck('code')
            ->values()
            ->all();
    }


    private function promoCodesPreview(): array
    {
        return PromoCode::query()
            ->select(['code', 'discount_type', 'discount_value', 'usage_limit', 'used_count', 'is_active', 'starts_at', 'ends_at'])
            ->get()
            ->map(fn (PromoCode $promoCode) => [
                'code' => (string) $promoCode->code,
                'discount_type' => (string) $promoCode->discount_type,
                'discount_value' => (float) $promoCode->discount_value,
                'usage_limit' => $promoCode->usage_limit,
                'used_count' => (int) $promoCode->used_count,
                'is_active' => (bool) $promoCode->is_active,
                'starts_at' => $promoCode->starts_at?->toIso8601String(),
                'ends_at' => $promoCode->ends_at?->toIso8601String(),
            ])
            ->values()
            ->all();
    }

    private function generateNumericOrderNumber(): string
    {
        do {
            // Short numeric format: yymmdd + 4 random digits (10 digits total)
            $candidate = now()->format('ymd').str_pad((string) random_int(0, 9999), 4, '0', STR_PAD_LEFT);
        } while (Order::query()->where('order_number', $candidate)->exists());

        return $candidate;
    }

    private function upsertCustomer(array $baseValidated): Customer
    {
        return Customer::updateOrCreate(
            ['email' => $baseValidated['email']],
            [
                'first_name' => $baseValidated['first_name'],
                'last_name' => $baseValidated['last_name'],
                'phone' => $baseValidated['phone'] ?? null,
            ]
        );
    }

    private function buyerDefaults(Request $request): array
    {
        $user = $request->user();
        $customer = Customer::query()->where('email', $user->email)->first();
        $nameParts = preg_split('/\s+/', trim((string) $user->name), 2) ?: [];

        $accountFirstName = trim((string) ($nameParts[0] ?? ''));
        $accountLastName = trim((string) ($nameParts[1] ?? ''));
        $accountPhone = trim((string) ($user->phone ?? ''));

        return [
            'first_name' => $accountFirstName !== '' ? $accountFirstName : ($customer?->first_name ?? ''),
            'last_name' => $accountLastName !== '' ? $accountLastName : ($customer?->last_name ?? ''),
            'email' => $user->email,
            'phone' => $accountPhone !== '' ? $accountPhone : ($customer?->phone ?? ''),
        ];
    }

    private function normalizeEventCart(Event $event, string $encodedCart): array
    {
        $decoded = base64_decode(strtr($encodedCart, '-_', '+/'), true);
        if ($decoded === false) {
            throw ValidationException::withMessages(['cart' => 'Invalid cart data.']);
        }

        $payload = json_decode($decoded, true);
        if (! is_array($payload)) {
            throw ValidationException::withMessages(['cart' => 'Invalid cart format.']);
        }

        // Merge repeated ticket rows so per-order limits cannot be bypassed by splitting a ticket across rows.
        $requested = collect($payload)
            ->filter(fn ($row) => is_array($row))
            ->map(fn ($row) => ['ticket_id' => (int) ($row['ticket_id'] ?? 0), 'qty' => max(1, (int) ($row['qty'] ?? 1))])
            ->filter(fn ($row) => $row['ticket_id'] > 0)
            ->groupBy('ticket_id')
            ->map(fn ($rows, $ticketId) => ['ticket_id' => (int) $ticketId, 'qty' => (int) $rows->sum('qty')])
            ->values();

        return $this->buildSelection($event, $requested);
    }

    /**
     * Single source of truth for what may be booked: bookable event, active tickets of that event,
     * couple tickets in pairs, per-ticket maximums. Fees are computed here, server side.
     */
    private function buildSelection(Event $event, \Illuminate\Support\Collection $requested): array
    {
        if (! $this->isEventBookable($event)) {
            throw ValidationException::withMessages(['cart' => 'Booking is closed for this event.']);
        }

        if ($requested->isEmpty()) {
            throw ValidationException::withMessages(['cart' => 'No tickets selected.']);
        }

        $tickets = EventTicket::query()
            ->where('event_id', $event->id)
            ->where('status', 'active')
            ->whereIn('id', $requested->pluck('ticket_id'))
            ->get()
            ->keyBy('id');

        $units = [];
        foreach ($requested as $row) {
            $ticket = $tickets->get($row['ticket_id']);
            if (! $ticket) {
                throw ValidationException::withMessages(['cart' => 'One of the selected tickets is no longer available.']);
            }

            $maxQuantity = $this->maxAllowedQuantityForTicket($ticket);
            if ((bool) ($ticket->is_couple ?? false) && (int) $row['qty'] !== 2) {
                throw ValidationException::withMessages([
                    'cart' => "{$ticket->name} is couples only and must be booked as 2 tickets.",
                ]);
            }

            if ((int) $row['qty'] > $maxQuantity) {
                throw ValidationException::withMessages([
                    'cart' => "Maximum allowed quantity for {$ticket->name} is {$maxQuantity}.",
                ]);
            }

            for ($i = 0; $i < $row['qty']; $i++) {
                $units[] = [
                    'ticket_id' => $ticket->id,
                    'ticket_name' => $ticket->name,
                    'ticket_price' => (float) $ticket->price,
                    'event_name' => $event->name,
                ];
            }
        }

        $subtotal = collect($units)->sum('ticket_price');
        $feeSummary = $this->calculateEventFeeSummary($event->loadMissing('fees'), (float) $subtotal);

        return [
            'event_id' => $event->id,
            'event_name' => $event->name,
            'requires_approval' => (bool) $event->requires_booking_approval,
            'units' => $units,
            'fees' => $feeSummary['breakdown'],
            'fees_total' => $feeSummary['total'],
        ];
    }

    /** Re-validates a stored selection (list of per-person units) against the current event and tickets. */
    private function assertSelectionStillValid(Event $event, \Illuminate\Support\Collection $units): void
    {
        $requested = $units
            ->groupBy(fn ($unit) => (int) ($unit['ticket_id'] ?? 0))
            ->map(fn ($rows, $ticketId) => ['ticket_id' => (int) $ticketId, 'qty' => $rows->count()])
            ->filter(fn ($row) => $row['ticket_id'] > 0)
            ->values();

        try {
            $this->buildSelection($event, $requested);
        } catch (ValidationException $e) {
            throw ValidationException::withMessages(['tickets' => collect($e->errors())->flatten()->first()]);
        }
    }

    private function hydrateEventSelectionWithFees(?array $eventSelection): ?array
    {
        if (! is_array($eventSelection) || empty($eventSelection)) {
            return $eventSelection;
        }

        $eventId = (int) ($eventSelection['event_id'] ?? 0);
        if ($eventId <= 0) {
            return $eventSelection;
        }

        if (array_key_exists('fees', $eventSelection) && array_key_exists('fees_total', $eventSelection)) {
            return $eventSelection;
        }

        $event = Event::query()->with('fees')->find($eventId);
        if (! $event) {
            return $eventSelection;
        }

        $subtotal = collect($eventSelection['units'] ?? [])->sum(fn ($unit) => (float) ($unit['ticket_price'] ?? 0));
        $feeSummary = $this->calculateEventFeeSummary($event, (float) $subtotal);

        $eventSelection['fees'] = $feeSummary['breakdown'];
        $eventSelection['fees_total'] = $feeSummary['total'];
        session(['checkout.event_selection' => $eventSelection]);

        return $eventSelection;
    }

    private function calculateEventFeeSummary(Event $event, float $subtotal): array
    {
        $breakdown = $event->fees
            ->map(function ($fee) use ($subtotal) {
                $value = (float) ($fee->value ?? 0);
                $amount = $fee->fee_type === 'percentage'
                    ? round(($subtotal * $value) / 100, 2)
                    : round($value, 2);

                return [
                    'name' => (string) $fee->name,
                    'fee_type' => (string) $fee->fee_type,
                    'value' => $value,
                    'amount' => max(0, $amount),
                ];
            })
            ->filter(fn (array $fee) => $fee['amount'] > 0)
            ->values()
            ->all();

        return [
            'breakdown' => $breakdown,
            'total' => array_reduce($breakdown, fn (float $carry, array $fee) => $carry + (float) $fee['amount'], 0.0),
        ];
    }

    /** Same rule the event page applies: active event that has not started yet. */
    private function isEventBookable(Event $event): bool
    {
        return $event->status === 'active'
            && $event->event_date !== null
            && ! $event->event_date->lt(now()->startOfDay());
    }

    private function maxAllowedQuantityForTicket(EventTicket $ticket): int
    {
        return (bool) ($ticket->is_couple ?? false) ? 2 : max(1, (int) ($ticket->max_per_order ?? 10));
    }
}
