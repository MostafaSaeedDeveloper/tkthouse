<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Mail\OrderApprovedMail;
use App\Mail\OrderNoteToCustomerMail;
use App\Mail\OrderRejectedMail;
use App\Models\EventTicket;
use App\Models\Order;
use App\Models\PaymentMethod;
use App\Models\PromoCode;
use App\Services\TicketIssuanceService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use Spatie\Activitylog\Models\Activity;

class OrderController extends Controller
{
    private const DELETED_ORDERS_PERMISSION = 'orders.deleted.view';
    private const SHOW_HIDDEN_ORDERS_PERMISSION = 'showing_orders';
    private const HIDDEN_ORDER_HISTORY_DESCRIPTIONS = [
        'Order soft deleted',
        'Order restored from trash',
    ];

    public function index(Request $request)
    {
        $ordersQuery = Order::query()->withCount('items')->with(['customer', 'items:id,order_id,ticket_name']);


        if ($request->filled('status')) {
            $ordersQuery->where('status', $request->string('status'));
        }

        if ($request->filled('payment_method')) {
            $ordersQuery->where('payment_method', $request->string('payment_method'));
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
        $deletedOrdersCount = $canViewDeletedOrders ? Order::onlyTrashed()->count() : 0;

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

        return view('admin.orders.index', compact('orders', 'ticketColorMap', 'paymentMethods', 'canViewDeletedOrders', 'deletedOrdersCount'));
    }

    public function deleted(Request $request)
    {
        abort_unless($request->user()?->can(self::DELETED_ORDERS_PERMISSION), 403);

        $orders = Order::onlyTrashed()
            ->withCount('items')
            ->with(['customer'])
            ->orderByDesc('deleted_at')
            ->paginate(15)
            ->withQueryString();

        return view('admin.orders.deleted', compact('orders'));
    }

    public function show(Request $request, Order $order)
    {
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

        return view('admin.orders.show', compact('order', 'notes', 'history', 'activityTimeline', 'paymentMethodLabel'));
    }

    public function edit(Request $request, Order $order)
    {
        $order->load(['customer', 'items.ticket', 'items.issuedTickets.dashboardTicket', 'user', 'promoCode']);

        $paymentMethods = PaymentMethod::query()
            ->where('code', '!=', 'card')
            ->where('is_active', true)
            ->orderBy('id')
            ->get(['code', 'name', 'checkout_label']);

        $promoCodes = PromoCode::query()->orderByDesc('is_active')->orderBy('code')->get(['id', 'code', 'discount_type', 'discount_value', 'is_active']);

        return view('admin.orders.edit', compact('order', 'paymentMethods', 'promoCodes'));
    }

    public function update(Request $request, Order $order)
    {
        $validated = $request->validate([
            'status' => ['required', 'in:pending_approval,pending_payment,on_hold,paid,canceled,rejected,refunded,partially_refunded'],
            'payment_method' => ['required', 'string', 'max:100'],
            'requires_approval' => ['nullable', 'boolean'],
            'exclude_from_statistics' => ['nullable', 'boolean'],
            'promo_code' => ['nullable', 'string', 'max:50'],
            'items' => ['array'],
            'items.*.id' => ['required', 'integer'],
            'items.*.quantity' => ['required', 'integer', 'min:1'],
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

        $order->update([
            'status' => $validated['status'],
            'payment_method' => $validated['payment_method'],
            'paid_at' => $paidAt,
            'requires_approval' => array_key_exists('requires_approval', $validated)
                ? (bool) $validated['requires_approval']
                : (bool) $order->requires_approval,
            'approved_at' => $validated['status'] === 'pending_payment' ? ($order->approved_at ?? now()) : null,
            'payment_link_token' => $validated['status'] === 'pending_payment' ? ($order->payment_link_token ?: Str::random(40)) : $order->payment_link_token,
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

            $lineTotal = ((float) $item->ticket_price) * (int) $updated['quantity'];
            $item->update([
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

        $order->update([
            'promo_code_id' => $selectedPromo?->id,
            'promo_code' => $selectedPromo?->code,
            'subtotal_amount' => $total,
            'discount_amount' => $discountAmount,
            'total_amount' => max(0, $total - $discountAmount),
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
        }

        app(TicketIssuanceService::class)->issueIfPaid($order);

        return redirect()->route('admin.orders.show', $order)->with('success', 'Order updated successfully.');
    }

    public function destroy(Request $request, Order $order)
    {
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
        $targetOrder->restore();

        activity('orders')
            ->performedOn($targetOrder)
            ->causedBy($request->user())
            ->log('Order restored from trash');

        return redirect()->route('admin.orders.deleted')->with('success', 'Order restored successfully.');
    }

    public function storeNote(Request $request, Order $order)
    {
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
        if ($order->status !== 'pending_approval') {
            return back()->with('error', 'Only pending approval orders can be approved.');
        }

        $oldStatus = $order->status;

        $order->update([
            'status' => 'pending_payment',
            'approved_at' => now(),
            'payment_link_token' => Str::random(40),
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

        return back()->with('success', 'Order approved and payment email sent successfully.');
    }

    public function reject(Request $request, Order $order)
    {
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

        Mail::to($order->customer->email)
            ->send(new OrderRejectedMail($order));

        return back()->with('success', 'Order rejected and email sent successfully.');
    }

}
