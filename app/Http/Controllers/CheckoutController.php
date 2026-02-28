<?php

namespace App\Http\Controllers;

use App\Models\Customer;
use App\Models\Event;
use App\Models\EventTicket;
use App\Models\Order;
use App\Models\PaymentMethod;
use App\Models\Ticket;
use App\Services\PaymobService;
use App\Services\TicketIssuanceService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\Rule;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class CheckoutController extends Controller
{
    public function index(Request $request)
    {
        if ($request->filled('event') && $request->filled('cart')) {
            $event = Event::query()->where('status', 'active')->findOrFail((int) $request->input('event'));
            $selection = $this->normalizeEventCart($event, (string) $request->input('cart'));
            session(['checkout.event_selection' => $selection]);
        }

        $eventSelection = session('checkout.event_selection');
        $buyer = $this->buyerDefaults($request);

        if ($eventSelection) {
            return view('front.checkout', [
                'mode' => 'event_locked',
                'eventSelection' => $eventSelection,
                'buyer' => $buyer,
                'activePaymentMethods' => PaymentMethod::query()->where('is_active', true)->where('code', '!=', 'card')->orderBy('id')->get(['name', 'code', 'checkout_label', 'checkout_icon', 'checkout_description']),
            ]);
        }

        $eventTickets = EventTicket::query()
            ->with('event:id,name,status,event_date,requires_booking_approval')
            ->where('status', 'active')
            ->get()
            ->filter(fn ($ticket) => $ticket->event && $ticket->event->status === 'active')
            ->sortBy(fn ($ticket) => [$ticket->event->event_date?->format('Y-m-d') ?? '9999-12-31', $ticket->name])
            ->values();

        $legacyTickets = Ticket::query()
            ->where('status', 'active')
            ->orderBy('name')
            ->get();

        return view('front.checkout', [
            'mode' => 'open',
            'eventTickets' => $eventTickets,
            'legacyTickets' => $legacyTickets,
            'eventSelection' => null,
            'buyer' => $buyer,
            'activePaymentMethods' => PaymentMethod::query()->where('is_active', true)->where('code', '!=', 'card')->orderBy('id')->get(['name', 'code', 'checkout_label', 'checkout_icon', 'checkout_description']),
        ]);
    }

    public function store(Request $request)
    {
        $baseValidated = $request->validate([
            'first_name' => ['required', 'string', 'max:255'],
            'last_name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255'],
            'phone' => ['nullable', 'string', 'max:255'],
        ]);

        $eventSelection = session('checkout.event_selection');
        if ($eventSelection) {
            return $this->storeFromLockedEventSelection($request, $baseValidated, $eventSelection);
        }

        return $this->storeFromOpenSelection($request, $baseValidated);
    }

    public function thankYou(Request $request)
    {
        return view('front.checkout-thank-you', [
            'flow' => (string) $request->query('flow', 'pending_review'),
            'orderNumber' => (string) $request->query('order'),
        ]);
    }

    public function paymentPage(Request $request, Order $order, string $token)
    {
        abort_unless($request->user() && (int) $order->user_id === (int) $request->user()->id, 403);
        abort_unless($order->payment_link_token && hash_equals($order->payment_link_token, $token), 404);
        abort_unless($order->status === 'pending_payment', 404);

        $order->load(['items', 'customer']);

        $activePaymentMethods = PaymentMethod::query()
            ->where('is_active', true)
            ->where('code', '!=', 'card')
            ->orderBy('id')
            ->get(['name', 'code', 'provider', 'checkout_label', 'checkout_icon', 'checkout_description']);

        $selectedMethod = $activePaymentMethods->firstWhere('code', $order->payment_method)?->code
            ?? $activePaymentMethods->first()?->code
            ?? $order->payment_method;

        return view('front.payment', [
            'order' => $order,
            'activePaymentMethods' => $activePaymentMethods,
            'selectedMethod' => $selectedMethod,
        ]);
    }

    public function paymobRedirect(Request $request, Order $order, string $token, PaymobService $paymobService)
    {
        abort_unless($request->user() && (int) $order->user_id === (int) $request->user()->id, 403);
        abort_unless($order->payment_link_token && hash_equals($order->payment_link_token, $token), 404);
        abort_unless($order->status === 'pending_payment', 404);

        $method = (string) $request->query('method', $order->payment_method);
        $isPaymobMethod = PaymentMethod::query()
            ->where('is_active', true)
            ->where('provider', 'paymob')
            ->where('code', $method)
            ->exists();

        if (! $isPaymobMethod) {
            return back()->withErrors(['payment' => 'Selected payment method is not available for online gateway.']);
        }

        if ($order->payment_method !== $method) {
            $order->update(['payment_method' => $method]);
        }

        try {
            $url = $paymobService->createCheckoutUrl($order->loadMissing('customer'));
        } catch (\Throwable $exception) {
            return back()->withErrors(['payment' => $exception->getMessage()]);
        }

        return redirect()->away($url);
    }

    public function paymobCallback(Request $request)
    {
        $payload = $request->all();
        $merchantOrderId = (string) data_get($payload, 'obj.order.merchant_order_id', data_get($payload, 'merchant_order_id', ''));
        $isSuccess = (bool) data_get($payload, 'obj.success', data_get($payload, 'success', false));

        if ($merchantOrderId === '') {
            Log::warning('Paymob callback received without merchant_order_id.', ['payload' => $payload]);

            if ($request->isMethod('get')) {
                return redirect()->route('front.checkout.thank-you', ['flow' => 'payment_failed']);
            }

            return response()->json(['received' => true, 'updated' => false]);
        }

        $order = $this->findOrderFromMerchantOrderId($merchantOrderId);
        if (! $order) {
            Log::warning('Paymob callback order not found.', ['merchant_order_id' => $merchantOrderId]);

            if ($request->isMethod('get')) {
                return redirect()->route('front.checkout.thank-you', ['flow' => 'payment_failed', 'order' => $merchantOrderId]);
            }

            return response()->json(['received' => true, 'updated' => false]);
        }

        if ($isSuccess && $order->status !== 'paid') {
            $order->update([
                'status' => 'paid',
            ]);
            app(TicketIssuanceService::class)->issueIfPaid($order);
        }

        Log::info('Paymob callback processed.', [
            'order_id' => $order->id,
            'merchant_order_id' => $merchantOrderId,
            'success' => $isSuccess,
        ]);

        if ($request->isMethod('get')) {
            return redirect()->route('front.checkout.thank-you', [
                'flow' => $isSuccess ? 'payment_success' : 'payment_failed',
                'order' => $order->order_number,
            ]);
        }

        return response()->json(['received' => true, 'updated' => $isSuccess]);
    }

    public function confirmPayment(Request $request, Order $order, string $token)
    {
        abort_unless($request->user() && (int) $order->user_id === (int) $request->user()->id, 403);
        abort_unless($order->payment_link_token && hash_equals($order->payment_link_token, $token), 404);
        abort_unless($order->status === 'pending_payment', 404);

        $validated = $request->validate([
            'payment_method' => ['required', Rule::in($this->activePaymentMethodCodes())],
        ]);

        $oldStatus = $order->status;

        if ($order->payment_method !== $validated['payment_method']) {
            $order->update(['payment_method' => $validated['payment_method']]);
        }

        $order->update([
            'status' => 'paid',
        ]);

        app(TicketIssuanceService::class)->issueIfPaid($order);

        activity('orders')
            ->performedOn($order)
            ->causedBy($request->user())
            ->withProperties([
                'from_status' => $oldStatus,
                'to_status' => $order->status,
            ])
            ->log('Payment confirmed');

        return redirect()->route('front.checkout.thank-you')->with('success', 'Payment completed successfully.');
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
        }

        if ($errors) {
            throw ValidationException::withMessages($errors);
        }

        $event = Event::query()->findOrFail((int) ($selection['event_id'] ?? 0));
        $requiresApproval = (bool) $event->requires_booking_approval;

        if (! $requiresApproval) {
            $request->validate([
                'payment_method' => ['required', Rule::in($this->activePaymentMethodCodes())],
            ]);
        }

        $order = DB::transaction(function () use ($request, $baseValidated, $ticketUnits, $attendees, $requiresApproval) {
            $customer = $this->upsertCustomer($baseValidated);

            $order = Order::create([
                'customer_id' => $customer->id,
                'user_id' => $request->user()->id,
                'affiliate_user_id' => $request->user()->referred_by_user_id,
                'order_number' => $this->generateNumericOrderNumber(),
                'status' => $requiresApproval ? 'pending_approval' : 'pending_payment',
                'requires_approval' => $requiresApproval,
                'payment_method' => $requiresApproval ? 'pending_review' : (string) $request->input('payment_method'),
                'payment_link_token' => $requiresApproval ? null : Str::random(40),
                'total_amount' => 0,
            ]);

            $total = 0;

            foreach ($ticketUnits as $index => $unit) {
                $attendee = $attendees[$index];
                $lineTotal = (float) $unit['ticket_price'];
                $total += $lineTotal;

                $order->items()->create([
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

            $order->update(['total_amount' => $total]);

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

        if ($order->status === 'pending_payment' && $order->payment_link_token) {
            return redirect()->route('front.orders.payment', ['order' => $order, 'token' => $order->payment_link_token]);
        }

        return redirect()->route('front.checkout.thank-you', ['flow' => 'pending_review', 'order' => $order->order_number])
            ->with('success', 'Your order has been submitted successfully.');
    }

    private function storeFromOpenSelection(Request $request, array $baseValidated)
    {
        $validated = $request->validate([
            'items' => ['required', 'array'],
            'items.*.ticket_key' => ['required', 'string'],
            'items.*.quantity' => ['required', 'integer', 'min:1'],
            'items.*.holder_name' => ['required', 'string', 'max:255'],
            'items.*.holder_email' => ['required', 'email', 'max:255'],
            'items.*.holder_phone' => ['nullable', 'string', 'max:255'],
        ]);

        $items = collect($validated['items'])->filter(fn ($item) => ! empty($item['ticket_key']))->values();

        if ($items->isEmpty()) {
            throw ValidationException::withMessages(['items' => 'Please add at least one ticket row.']);
        }

        $grouped = $items->map(function ($item) {
            if (! preg_match('/^(event|legacy):(\d+)$/', $item['ticket_key'], $matches)) {
                throw ValidationException::withMessages(['items' => 'Invalid ticket selection.']);
            }

            return $item + ['ticket_type' => $matches[1], 'ticket_ref_id' => (int) $matches[2]];
        });

        $eventTicketIds = $grouped->where('ticket_type', 'event')->pluck('ticket_ref_id')->all();
        $legacyTicketIds = $grouped->where('ticket_type', 'legacy')->pluck('ticket_ref_id')->all();

        $eventTickets = EventTicket::query()
            ->with('event:id,name,status,requires_booking_approval')
            ->whereIn('id', $eventTicketIds)
            ->where('status', 'active')
            ->get()
            ->keyBy('id');

        $legacyTickets = Ticket::query()
            ->whereIn('id', $legacyTicketIds)
            ->where('status', 'active')
            ->get()
            ->keyBy('id');

        $requiresApproval = $grouped
            ->where('ticket_type', 'event')
            ->contains(fn ($item) => (bool) optional($eventTickets->get($item['ticket_ref_id']))->event?->requires_booking_approval);

        if (! $requiresApproval) {
            $request->validate([
                'payment_method' => ['required', Rule::in($this->activePaymentMethodCodes())],
            ]);
        }

        $order = DB::transaction(function () use ($request, $baseValidated, $grouped, $eventTickets, $legacyTickets, $requiresApproval) {
            $customer = $this->upsertCustomer($baseValidated);

            $order = Order::create([
                'customer_id' => $customer->id,
                'user_id' => $request->user()->id,
                'affiliate_user_id' => $request->user()->referred_by_user_id,
                'order_number' => $this->generateNumericOrderNumber(),
                'status' => $requiresApproval ? 'pending_approval' : 'pending_payment',
                'requires_approval' => $requiresApproval,
                'payment_method' => $requiresApproval ? 'pending_review' : (string) $request->input('payment_method'),
                'payment_link_token' => $requiresApproval ? null : Str::random(40),
                'total_amount' => 0,
            ]);

            $total = 0;

            foreach ($grouped as $item) {
                if ($item['ticket_type'] === 'event') {
                    $ticket = $eventTickets->get($item['ticket_ref_id']);
                    if (! $ticket || ! $ticket->event || $ticket->event->status !== 'active') {
                        throw ValidationException::withMessages(['items' => 'Selected event ticket is no longer available.']);
                    }
                    $ticketName = ($ticket->event->name ? $ticket->event->name.' - ' : '').$ticket->name;
                    $ticketPrice = $ticket->price;
                    $orderTicketId = null;
                } else {
                    $ticket = $legacyTickets->get($item['ticket_ref_id']);
                    if (! $ticket) {
                        throw ValidationException::withMessages(['items' => 'Selected ticket is no longer available.']);
                    }
                    $ticketName = $ticket->name;
                    $ticketPrice = $ticket->price;
                    $orderTicketId = $ticket->id;
                }

                $lineTotal = $ticketPrice * $item['quantity'];
                $total += $lineTotal;

                $order->items()->create([
                    'ticket_id' => $orderTicketId,
                    'ticket_name' => $ticketName,
                    'ticket_price' => $ticketPrice,
                    'quantity' => $item['quantity'],
                    'line_total' => $lineTotal,
                    'holder_name' => $item['holder_name'],
                    'holder_email' => $item['holder_email'],
                    'holder_phone' => $item['holder_phone'] ?: null,
                    'holder_gender' => null,
                    'holder_social_profile' => null,
                ]);
            }

            $order->update(['total_amount' => $total]);

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

        if ($order->status === 'pending_payment' && $order->payment_link_token) {
            return redirect()->route('front.orders.payment', ['order' => $order, 'token' => $order->payment_link_token]);
        }

        return redirect()->route('front.checkout.thank-you', ['flow' => 'pending_review', 'order' => $order->order_number])
            ->with('success', 'Your order has been submitted successfully.');
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

        return [
            'first_name' => $customer?->first_name ?? ($nameParts[0] ?? ''),
            'last_name' => $customer?->last_name ?? ($nameParts[1] ?? ''),
            'email' => $user->email,
            'phone' => $customer?->phone ?? '',
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

        $requested = collect($payload)
            ->map(fn ($row) => ['ticket_id' => (int) ($row['ticket_id'] ?? 0), 'qty' => max(1, (int) ($row['qty'] ?? 1))])
            ->filter(fn ($row) => $row['ticket_id'] > 0)
            ->values();

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
                continue;
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

        if (empty($units)) {
            throw ValidationException::withMessages(['cart' => 'Selected tickets are no longer available.']);
        }

        return [
            'event_id' => $event->id,
            'event_name' => $event->name,
            'requires_approval' => (bool) $event->requires_booking_approval,
            'units' => $units,
        ];
    }
}
