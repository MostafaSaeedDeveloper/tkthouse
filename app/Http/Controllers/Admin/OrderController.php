<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Mail\OrderApprovedMail;
use App\Mail\OrderNoteToCustomerMail;
use App\Mail\OrderRejectedMail;
use App\Mail\OrderStatusChangedMail;
use App\Models\Event;
use App\Models\EventTicket;
use App\Models\Order;
use App\Models\PaymentMethod;
use App\Models\PromoCode;
use App\Models\User;
use App\Services\PendingPaymentExpiryService;
use App\Services\TicketIssuanceService;
use App\Support\SystemSettings;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use Spatie\Activitylog\Models\Activity;
use Symfony\Component\HttpFoundation\StreamedResponse;

class OrderController extends Controller
{
    private const DELETED_ORDERS_PERMISSION = 'orders.deleted.view';
    private const SHOW_HIDDEN_ORDERS_PERMISSION = 'showing_orders';
    private const HIDDEN_ORDER_HISTORY_DESCRIPTIONS = [
        'Order soft deleted',
        'Order restored from trash',
    ];
    private const ORDER_STATUS_OPTIONS = [
        'pending_approval' => 'Pending Approval',
        'pending_payment' => 'Pending Payment',
        'on_hold' => 'On Hold',
        'paid' => 'Paid',
        'refunded' => 'Refunded',
        'partially_refunded' => 'Partially Refunded',
        'canceled' => 'Canceled',
        'rejected' => 'Rejected',
    ];

    public function index(Request $request)
    {
        app(PendingPaymentExpiryService::class)->expireDueOrders();

        $managedEvent = $request->user()?->managedEvent;

        $ordersQuery = Order::query()->withCount('items')->with(['customer', 'items:id,order_id,ticket_name']);
        $this->applyEventScopeToOrdersQuery($ordersQuery, $managedEvent);


        $canFilterByEvent = $request->user()?->can(self::SHOW_HIDDEN_ORDERS_PERMISSION) ?? false;

        if ($request->filled('status')) {
            $ordersQuery->where('status', $request->string('status'));
        }

        if ($request->filled('payment_method')) {
            $ordersQuery->where('payment_method', $request->string('payment_method'));
        }

        if ($canFilterByEvent && $request->filled('event_id')) {
            $ordersQuery->whereHas('items', function ($query) use ($request) {
                $query->where('event_id', $request->integer('event_id'));
            });
        }

        if ($request->filled('search')) {
            $search = trim((string) $request->input('search'));
            $ordersQuery->where(function ($query) use ($search) {
                $query->where('order_number', 'like', "%{$search}%")
                    ->orWhereHas('customer', function ($customerQuery) use ($search) {
                        $customerQuery->where('email', 'like', "%{$search}%")
                            ->orWhere('first_name', 'like', "%{$search}%")
                            ->orWhere('last_name', 'like', "%{$search}%");
                    });
            });
        }

        $orders = $ordersQuery->orderByDesc('id')->paginate(15)->withQueryString();
        $canViewDeletedOrders = $request->user()?->can(self::DELETED_ORDERS_PERMISSION) ?? false;
        $deletedOrdersCount = 0;
        if ($canViewDeletedOrders) {
            $deletedQuery = Order::onlyTrashed();
            $this->applyEventScopeToOrdersQuery($deletedQuery, $managedEvent);
            $deletedOrdersCount = $deletedQuery->count();
        }

        $ticketColorMap = EventTicket::query()
            ->select('name', 'color')
            ->get()
            ->mapWithKeys(fn (EventTicket $ticket) => [mb_strtolower(trim($ticket->name)) => $ticket->color ?: '#0d6efd'])
            ->all();

        $paymentMethods = PaymentMethod::query()
            ->where('code', '!=', 'card')
            ->where('is_active', true)
            ->orderBy('id')
            ->get(['code', 'name', 'checkout_label']);

        $events = $canFilterByEvent
            ? Event::query()->orderBy('name')->get(['id', 'name'])
            : collect();

        $canExport = $this->isSuperAdmin($request->user());

        return view('admin.orders.index', compact('orders', 'ticketColorMap', 'paymentMethods', 'canViewDeletedOrders', 'deletedOrdersCount', 'events', 'canFilterByEvent', 'canExport'));
    }

    public function deleted(Request $request)
    {
        abort_unless($request->user()?->can(self::DELETED_ORDERS_PERMISSION), 403);

        $managedEvent = $request->user()?->managedEvent;

        $ordersQuery = Order::onlyTrashed()
            ->withCount('items')
            ->with(['customer']);
        $this->applyEventScopeToOrdersQuery($ordersQuery, $managedEvent);

        $canFilterByEvent = $request->user()?->can(self::SHOW_HIDDEN_ORDERS_PERMISSION) ?? false;

        if ($request->filled('status')) {
            $ordersQuery->where('status', $request->string('status'));
        }

        if ($request->filled('payment_method')) {
            $ordersQuery->where('payment_method', $request->string('payment_method'));
        }

        if ($canFilterByEvent && $request->filled('event_id')) {
            $ordersQuery->whereHas('items', function ($query) use ($request) {
                $query->where('event_id', $request->integer('event_id'));
            });
        }

        if ($request->filled('search')) {
            $search = trim((string) $request->input('search'));
            $ordersQuery->where(function ($query) use ($search) {
                $query->where('order_number', 'like', "%{$search}%")
                    ->orWhereHas('customer', function ($customerQuery) use ($search) {
                        $customerQuery->where('email', 'like', "%{$search}%")
                            ->orWhere('first_name', 'like', "%{$search}%")
                            ->orWhere('last_name', 'like', "%{$search}%");
                    });
            });
        }

        $orders = $ordersQuery
            ->orderByDesc('deleted_at')
            ->paginate(15)
            ->withQueryString();

        $paymentMethods = PaymentMethod::query()
            ->where('code', '!=', 'card')
            ->where('is_active', true)
            ->orderBy('id')
            ->get(['code', 'name', 'checkout_label']);

        $events = $canFilterByEvent
            ? Event::query()->orderBy('name')->get(['id', 'name'])
            : collect();

        return view('admin.orders.deleted', compact('orders', 'paymentMethods', 'events', 'canFilterByEvent'));
    }

    public function show(Request $request, Order $order)
    {
        $this->abortIfOrderOutsideManagedEvent($request, $order);

        $order->load(['customer', 'items.ticket', 'items.issuedTickets.dashboardTicket', 'user', 'promoCode']);

        $paymentMethodLabel = PaymentMethod::query()
            ->where('code', (string) $order->payment_method)
            ->value('checkout_label');

        $paymentMethodLabel = trim((string) $paymentMethodLabel);
        if ($paymentMethodLabel === '') {
            $paymentMethodLabel = ucwords(str_replace('_', ' ', (string) $order->payment_method));
        }

        $activities = Activity::query()
            ->with('causer')
            ->forSubject($order)
            ->latest()
            ->get();

        $notes = $activities->where('log_name', 'order_notes')->values();
        $history = $activities
            ->where('log_name', '!=', 'order_notes')
            ->reject(fn (Activity $activity) => in_array($activity->description, self::HIDDEN_ORDER_HISTORY_DESCRIPTIONS, true))
            ->values();

        $statusTransitions = $history
            ->filter(fn ($log) => filled(data_get($log->properties, 'to_status')))
            ->sortBy('created_at')
            ->values();

        $submittedAt = optional($history->firstWhere('description', 'Order submitted'))->created_at ?? $order->created_at;
        $approvalQueuedAt = optional($statusTransitions->firstWhere('properties.to_status', 'pending_approval'))->created_at;
        $paymentLinkSentAt = optional($statusTransitions->firstWhere('properties.to_status', 'pending_payment'))->created_at;
        $paymentConfirmedAt = optional($statusTransitions->firstWhere('properties.to_status', 'paid'))->created_at;

        $activityTimeline = collect([
            [
                'label' => 'Order submitted',
                'at' => $submittedAt,
                'done' => true,
            ],
            [
                'label' => $order->requires_approval ? 'Awaiting admin approval' : 'Awaiting payment',
                'at' => $order->requires_approval ? ($approvalQueuedAt ?? $order->created_at) : $order->created_at,
                'done' => true,
            ],
            [
                'label' => 'Payment link sent',
                'at' => $paymentLinkSentAt,
                'done' => filled($paymentLinkSentAt),
            ],
            [
                'label' => 'Payment confirmed',
                'at' => $paymentConfirmedAt,
                'done' => filled($paymentConfirmedAt),
            ],
        ]);

        $statusOptions = self::ORDER_STATUS_OPTIONS;

        return view('admin.orders.show', compact('order', 'notes', 'history', 'activityTimeline', 'paymentMethodLabel', 'statusOptions'));
    }

    public function edit(Request $request, Order $order)
    {
        $this->abortIfOrderOutsideManagedEvent($request, $order);

        $order->load(['customer', 'items.ticket', 'items.issuedTickets.dashboardTicket', 'user', 'promoCode']);

        $paymentMethods = PaymentMethod::query()
            ->where('code', '!=', 'card')
            ->where('is_active', true)
            ->orderBy('id')
            ->get(['code', 'name', 'checkout_label']);

        $promoCodes = PromoCode::query()->orderByDesc('is_active')->orderBy('code')->get(['id', 'code', 'discount_type', 'discount_value', 'is_active']);

        $statusOptions = self::ORDER_STATUS_OPTIONS;
        $baseTotal = max(0, (float) $order->subtotal_amount - (float) $order->discount_amount);
        $existingExtraFees = max(0, (float) $order->total_amount - $baseTotal);

        return view('admin.orders.edit', compact('order', 'paymentMethods', 'promoCodes', 'statusOptions', 'existingExtraFees'));
    }

    public function update(Request $request, Order $order)
    {
        $this->abortIfOrderOutsideManagedEvent($request, $order);

        $validated = $request->validate([
            'status' => ['required', 'in:pending_approval,pending_payment,on_hold,paid,canceled,rejected,refunded,partially_refunded'],
            'payment_method' => ['required', 'string', 'max:100'],
            'requires_approval' => ['nullable', 'boolean'],
            'exclude_from_statistics' => ['nullable', 'boolean'],
            'promo_code' => ['nullable', 'string', 'max:50'],
            'extra_fees' => ['nullable', 'numeric', 'min:0'],
            'items' => ['array'],
            'items.*.id' => ['required', 'integer'],
            'items.*.quantity' => ['required', 'integer', 'min:1'],
            'items.*.ticket_price' => ['nullable', 'numeric', 'min:0'],
            'items.*.holder_name' => ['required', 'string', 'max:255'],
            'items.*.holder_email' => ['required', 'email', 'max:255'],
            'items.*.holder_phone' => ['nullable', 'string', 'max:255'],
        ]);

        $oldStatus = $order->status;
        $oldPaymentMethod = $order->payment_method;

        $paidAt = $order->paid_at;
        if ($validated['status'] === 'paid' && $oldStatus !== 'paid') {
            $paidAt = now();
        }

        $paymentTimeoutStartedAt = null;
        $paymentTimeoutMinutes = null;
        if ($validated['status'] === 'pending_payment') {
            $paymentTimeoutStartedAt = $oldStatus === 'pending_payment'
                ? ($order->payment_timeout_started_at ?? now())
                : now();
            $paymentTimeoutMinutes = $oldStatus === 'pending_payment'
                ? ($order->payment_timeout_minutes ?: SystemSettings::pendingPaymentTimeoutMinutes())
                : SystemSettings::pendingPaymentTimeoutMinutes();
        }

        $order->update([
            'status' => $validated['status'],
            'payment_method' => $validated['payment_method'],
            'paid_at' => $paidAt,
            'requires_approval' => array_key_exists('requires_approval', $validated)
                ? (bool) $validated['requires_approval']
                : (bool) $order->requires_approval,
            'approved_at' => $validated['status'] === 'pending_payment' ? ($order->approved_at ?? now()) : null,
            'payment_link_token' => $validated['status'] === 'pending_payment' ? ($order->payment_link_token ?: Str::random(40)) : $order->payment_link_token,
            'payment_timeout_started_at' => $paymentTimeoutStartedAt,
            'payment_timeout_minutes' => $paymentTimeoutMinutes,
            'exclude_from_statistics' => ($request->user()?->can(self::SHOW_HIDDEN_ORDERS_PERMISSION) ?? false)
                ? (bool) ($validated['exclude_from_statistics'] ?? false)
                : (bool) $order->exclude_from_statistics,
        ]);

        $itemsInput = collect($validated['items'] ?? [])->keyBy('id');

        $order->load('items');

        $total = (float) $order->items->sum(static fn ($item) => (float) $item->line_total);

        if ($itemsInput->isNotEmpty()) {
            $total = 0;
        }

        foreach ($order->items as $item) {
            $updated = $itemsInput->get($item->id);
            if (! $updated) {
                if ($itemsInput->isNotEmpty()) {
                    $total += (float) $item->line_total;
                }

                continue;
            }

            $ticketPrice = array_key_exists('ticket_price', $updated)
                ? (float) $updated['ticket_price']
                : (float) $item->ticket_price;

            $lineTotal = $ticketPrice * (int) $updated['quantity'];
            $item->update([
                'ticket_price' => $ticketPrice,
                'quantity' => (int) $updated['quantity'],
                'line_total' => $lineTotal,
                'holder_name' => $updated['holder_name'],
                'holder_email' => $updated['holder_email'],
                'holder_phone' => $updated['holder_phone'] ?: null,
            ]);

            $total += $lineTotal;
        }

        $promoCodeInput = strtoupper(trim((string) ($validated['promo_code'] ?? '')));
        $selectedPromo = null;
        $discountAmount = 0.0;

        if ($promoCodeInput !== '') {
            $selectedPromo = PromoCode::query()->where('code', $promoCodeInput)->first();
            if (! $selectedPromo) {
                return back()->withErrors(['promo_code' => 'Promo code does not exist.'])->withInput();
            }

            $discountAmount = $selectedPromo->discount_type === 'percent'
                ? round(($total * (float) $selectedPromo->discount_value) / 100, 2)
                : round((float) $selectedPromo->discount_value, 2);

            $discountAmount = min($discountAmount, $total);
        }

        if ($order->promo_code_id && (! $selectedPromo || (int) $order->promo_code_id !== (int) $selectedPromo->id)) {
            PromoCode::query()->whereKey($order->promo_code_id)->where('used_count', '>', 0)->decrement('used_count');
        }

        if ($selectedPromo && (int) $order->promo_code_id !== (int) $selectedPromo->id) {
            $selectedPromo->increment('used_count');
        }

        $extraFees = (float) ($validated['extra_fees'] ?? 0);

        $order->update([
            'promo_code_id' => $selectedPromo?->id,
            'promo_code' => $selectedPromo?->code,
            'subtotal_amount' => $total,
            'discount_amount' => $discountAmount,
            'total_amount' => max(0, ($total - $discountAmount) + $extraFees),
        ]);

        activity('orders')
            ->performedOn($order)
            ->causedBy($request->user())
            ->withProperties([
                'from_status' => $oldStatus,
                'to_status' => $order->status,
                'from_payment_method' => $oldPaymentMethod,
                'to_payment_method' => $order->payment_method,
                'total_amount' => (float) $order->total_amount,
            ])
            ->log('Order updated');

        if ($oldStatus !== $order->status) {
            activity('orders')
                ->performedOn($order)
                ->causedBy($request->user())
                ->withProperties([
                    'from_status' => $oldStatus,
                    'to_status' => $order->status,
                ])
                ->log('Order status changed');

            $this->sendOrderStatusChangedMail($order, $oldStatus, (string) $order->status);
        }

        app(TicketIssuanceService::class)->issueIfPaid($order);

        return redirect()->route('admin.orders.show', $order)->with('success', 'Order updated successfully.');
    }

    public function destroy(Request $request, Order $order)
    {
        $this->abortIfOrderOutsideManagedEvent($request, $order);

        abort_unless($request->user()?->can('orders.delete'), 403);

        $orderNumber = $order->order_number;
        $order->delete();

        activity('orders')
            ->performedOn($order)
            ->causedBy($request->user())
            ->log('Order soft deleted');

        return redirect()->route('admin.orders.index')->with('success', "Order {$orderNumber} deleted successfully.");
    }

    public function restore(Request $request, int $order)
    {
        abort_unless($request->user()?->can(self::DELETED_ORDERS_PERMISSION), 403);

        $targetOrder = Order::onlyTrashed()->findOrFail($order);
        $this->abortIfOrderOutsideManagedEvent($request, $targetOrder);
        $targetOrder->restore();

        activity('orders')
            ->performedOn($targetOrder)
            ->causedBy($request->user())
            ->log('Order restored from trash');

        return redirect()->route('admin.orders.deleted')->with('success', 'Order restored successfully.');
    }

    public function storeNote(Request $request, Order $order)
    {
        $this->abortIfOrderOutsideManagedEvent($request, $order);

        $validated = $request->validate([
            'body' => ['required', 'string', 'max:2000'],
            'send_to_customer' => ['nullable', 'boolean'],
        ]);

        $sendToCustomer = (bool) ($validated['send_to_customer'] ?? false);

        activity('order_notes')
            ->performedOn($order)
            ->causedBy($request->user())
            ->withProperties([
                'body' => $validated['body'],
                'send_to_customer' => $sendToCustomer,
            ])
            ->log('Order note added');

        if ($sendToCustomer && filled($order->customer?->email)) {
            Mail::to($order->customer->email)->send(new OrderNoteToCustomerMail($order, $validated['body']));

            return back()->with('success', 'Note added and emailed to customer successfully.');
        }

        return back()->with('success', 'Note added successfully.');
    }

    public function approve(Request $request, Order $order)
    {
        $this->abortIfOrderOutsideManagedEvent($request, $order);

        if ($order->status !== 'pending_approval') {
            return back()->with('error', 'Only pending approval orders can be approved.');
        }

        $oldStatus = $order->status;

        $order->update([
            'status' => 'pending_payment',
            'approved_at' => now(),
            'payment_link_token' => Str::random(40),
            'payment_timeout_started_at' => now(),
            'payment_timeout_minutes' => SystemSettings::pendingPaymentTimeoutMinutes(),
        ]);

        activity('orders')
            ->performedOn($order)
            ->causedBy($request->user())
            ->withProperties([
                'from_status' => $oldStatus,
                'to_status' => $order->status,
            ])
            ->log('Order approved and payment link created');

        $paymentLink = route('front.orders.payment', ['order' => $order, 'token' => $order->payment_link_token]);

        Mail::to($order->customer->email)
            ->send(new OrderApprovedMail($order, $paymentLink));

        $this->sendOrderStatusChangedMail($order, $oldStatus, (string) $order->status);

        return back()->with('success', 'Order approved and payment email sent successfully.');
    }

    public function reject(Request $request, Order $order)
    {
        $this->abortIfOrderOutsideManagedEvent($request, $order);

        if ($order->status !== 'pending_approval') {
            return back()->with('error', 'Only pending approval orders can be rejected.');
        }

        $oldStatus = $order->status;
        $order->update([
            'status' => 'rejected',
            'approved_at' => null,
        ]);

        activity('orders')
            ->performedOn($order)
            ->causedBy($request->user())
            ->withProperties([
                'from_status' => $oldStatus,
                'to_status' => $order->status,
            ])
            ->log('Order rejected');

        $this->sendOrderStatusChangedMail($order, $oldStatus, (string) $order->status);

        return back()->with('success', 'Order rejected and email sent successfully.');
    }

    public function updateStatus(Request $request, Order $order)
    {
        $this->abortIfOrderOutsideManagedEvent($request, $order);

        $validated = $request->validate([
            'status' => ['required', 'in:pending_approval,pending_payment,on_hold,paid,canceled,rejected,refunded,partially_refunded'],
        ]);

        $oldStatus = (string) $order->status;
        $newStatus = (string) $validated['status'];

        if ($oldStatus === $newStatus) {
            return back()->with('success', 'Order status is already set to the selected value.');
        }

        $paidAt = $order->paid_at;
        if ($newStatus === 'paid' && $oldStatus !== 'paid') {
            $paidAt = now();
        }

        $paymentTimeoutStartedAt = null;
        $paymentTimeoutMinutes = null;
        if ($newStatus === 'pending_payment') {
            $paymentTimeoutStartedAt = $oldStatus === 'pending_payment'
                ? ($order->payment_timeout_started_at ?? now())
                : now();
            $paymentTimeoutMinutes = $oldStatus === 'pending_payment'
                ? ($order->payment_timeout_minutes ?: SystemSettings::pendingPaymentTimeoutMinutes())
                : SystemSettings::pendingPaymentTimeoutMinutes();
        }

        $order->update([
            'status' => $newStatus,
            'paid_at' => $paidAt,
            'approved_at' => $newStatus === 'pending_payment' ? ($order->approved_at ?? now()) : null,
            'payment_link_token' => $newStatus === 'pending_payment' ? ($order->payment_link_token ?: Str::random(40)) : $order->payment_link_token,
            'payment_timeout_started_at' => $paymentTimeoutStartedAt,
            'payment_timeout_minutes' => $paymentTimeoutMinutes,
        ]);

        activity('orders')
            ->performedOn($order)
            ->causedBy($request->user())
            ->withProperties([
                'from_status' => $oldStatus,
                'to_status' => $newStatus,
            ])
            ->log('Order status changed');

        $this->sendOrderStatusChangedMail($order, $oldStatus, $newStatus);
        app(TicketIssuanceService::class)->issueIfPaid($order);

        return back()->with('success', 'Order status updated successfully.');
    }



    public function export(Request $request): StreamedResponse
    {
        abort_unless($this->isSuperAdmin($request->user()), 403);

        $managedEvent = $request->user()?->managedEvent;
        $canFilterByEvent = $request->user()?->can(self::SHOW_HIDDEN_ORDERS_PERMISSION) ?? false;

        $ordersQuery = Order::query()->with(['customer', 'items.event']);
        $this->applyEventScopeToOrdersQuery($ordersQuery, $managedEvent);

        if ($request->filled('status')) {
            $ordersQuery->where('status', $request->string('status'));
        }

        if ($request->filled('payment_method')) {
            $ordersQuery->where('payment_method', $request->string('payment_method'));
        }

        if ($canFilterByEvent && $request->filled('event_id')) {
            $ordersQuery->whereHas('items', function ($query) use ($request) {
                $query->where('event_id', $request->integer('event_id'));
            });
        }

        if ($request->filled('search')) {
            $search = trim((string) $request->input('search'));
            $ordersQuery->where(function ($query) use ($search) {
                $query->where('order_number', 'like', "%{$search}%")
                    ->orWhereHas('customer', function ($customerQuery) use ($search) {
                        $customerQuery->where('email', 'like', "%{$search}%")
                            ->orWhere('first_name', 'like', "%{$search}%")
                            ->orWhere('last_name', 'like', "%{$search}%");
                    });
            });
        }

        $orders = $ordersQuery->orderByDesc('id')->get();

        $availableColumns = [
            'order_number'   => 'Order #',
            'customer_name'  => 'Customer Name',
            'customer_email' => 'Customer Email',
            'customer_phone' => 'Customer Phone',
            'event'          => 'Event',
            'ticket_types'   => 'Ticket Types',
            'items_count'    => 'Items',
            'total'          => 'Total Amount',
            'status'         => 'Status',
            'payment_method' => 'Payment Method',
            'created_at'     => 'Created At',
            'paid_at'        => 'Paid At',
        ];

        $selected = collect($request->input('columns', array_keys($availableColumns)))
            ->filter(fn ($col) => array_key_exists($col, $availableColumns))
            ->values()
            ->all();

        if (empty($selected)) {
            $selected = array_keys($availableColumns);
        }

        return response()->streamDownload(function () use ($orders, $selected, $availableColumns) {
            $handle = fopen('php://output', 'w');

            fputcsv($handle, array_map(fn ($col) => $availableColumns[$col], $selected));

            foreach ($orders as $order) {
                $eventNames = $order->items
                    ->map(fn ($item) => str_contains((string) $item->ticket_name, ' - ')
                        ? trim((string) str($item->ticket_name)->before(' - '))
                        : null)
                    ->filter()
                    ->unique()
                    ->implode(', ');

                $ticketTypes = $order->items
                    ->pluck('ticket_name')
                    ->unique()
                    ->implode(', ');

                $row = [];
                foreach ($selected as $col) {
                    $row[] = match ($col) {
                        'order_number'   => preg_replace('/\D+/', '', (string) $order->order_number) ?: $order->order_number,
                        'customer_name'  => $order->customer?->full_name ?? '',
                        'customer_email' => $order->customer?->email ?? '',
                        'customer_phone' => $order->customer?->phone ?? '',
                        'event'          => $eventNames,
                        'ticket_types'   => $ticketTypes,
                        'items_count'    => $order->items->count(),
                        'total'          => number_format((float) $order->total_amount, 2),
                        'status'         => $order->status,
                        'payment_method' => $order->payment_method,
                        'created_at'     => optional($order->created_at)->format('Y-m-d H:i'),
                        'paid_at'        => optional($order->paid_at)->format('Y-m-d H:i') ?? '',
                        default          => '',
                    };
                }
                fputcsv($handle, $row);
            }

            fclose($handle);
        }, 'orders-export-' . now()->format('Y-m-d_H-i') . '.csv', ['Content-Type' => 'text/csv']);
    }

    private function isSuperAdmin(?User $user): bool
    {
        if (! $user) {
            return false;
        }
        $normalized = strtolower((string) preg_replace('/[^a-z0-9]/i', '', trim((string) $user->username)));
        if ($normalized === 'superadmin') {
            return true;
        }
        return $user->roles->contains(fn ($role) =>
            strtolower((string) preg_replace('/[^a-z0-9]/i', '', trim((string) $role->name))) === 'superadmin'
        );
    }

    private function applyEventScopeToOrdersQuery(Builder $query, ?Event $event): void
    {
        if (! $event) {
            return;
        }

        $query->whereHas('items', function (Builder $itemsQuery) use ($event) {
            $itemsQuery->where('event_id', $event->id);
        });
    }

    private function abortIfOrderOutsideManagedEvent(Request $request, Order $order): void
    {
        $event = $request->user()?->managedEvent;
        if (! $event) {
            return;
        }

        $belongs = $order->items()
            ->where('event_id', $event->id)
            ->exists();

        abort_unless($belongs, 403);
    }

    private function sendOrderStatusChangedMail(Order $order, string $oldStatus, string $newStatus): void
    {
        if ($oldStatus === $newStatus || blank($order->customer?->email)) {
            return;
        }

        if ($newStatus === 'rejected') {
            Mail::to($order->customer->email)
                ->send(new OrderRejectedMail($order));

            return;
        }

        Mail::to($order->customer->email)
            ->send(new OrderStatusChangedMail($order, $oldStatus, $newStatus));
    }

}
