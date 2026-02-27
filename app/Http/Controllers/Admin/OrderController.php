<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Services\TicketIssuanceService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use Spatie\Activitylog\Models\Activity;

class OrderController extends Controller
{
    public function index(Request $request)
    {
        $orders = Order::query()
            ->withCount('items')
            ->with('customer')
            ->when($request->filled('status'), fn ($query) => $query->where('status', $request->string('status')))
            ->when($request->filled('payment_method'), fn ($query) => $query->where('payment_method', $request->string('payment_method')))
            ->when($request->filled('search'), function ($query) use ($request) {
                $search = trim((string) $request->input('search'));

                $query->where(function ($nestedQuery) use ($search) {
                    $nestedQuery->where('order_number', 'like', "%{$search}%")
                        ->orWhereHas('customer', function ($customerQuery) use ($search) {
                            $customerQuery->where('full_name', 'like', "%{$search}%")
                                ->orWhere('email', 'like', "%{$search}%");
                        });
                });
            })
            ->latest()
            ->paginate(15)
            ->withQueryString();

        return view('admin.orders.index', compact('orders'));
    }

    public function show(Order $order)
    {
        $order->load(['customer', 'items.ticket', 'user']);

        $activities = Activity::query()
            ->with('causer')
            ->forSubject($order)
            ->latest()
            ->get();

        $notes = $activities->where('log_name', 'order_notes')->values();
        $history = $activities->where('log_name', '!=', 'order_notes')->values();

        $statusTransitions = $history
            ->filter(fn ($log) => filled(data_get($log->properties, 'to_status')))
            ->sortBy('created_at')
            ->values();

        $submittedAt = optional($history->firstWhere('description', 'Order submitted'))->created_at ?? $order->created_at;
        $approvalQueuedAt = optional($statusTransitions->firstWhere('properties.to_status', 'pending_approval'))->created_at;
        $paymentLinkSentAt = optional($statusTransitions->firstWhere('properties.to_status', 'pending_payment'))->created_at;
        $paymentConfirmedAt = optional($statusTransitions->firstWhere('properties.to_status', 'complete'))->created_at;

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

        return view('admin.orders.show', compact('order', 'notes', 'history', 'activityTimeline'));
    }

    public function edit(Order $order)
    {
        $order->load(['customer', 'items.ticket', 'user']);

        return view('admin.orders.edit', compact('order'));
    }

    public function update(Request $request, Order $order)
    {
        $validated = $request->validate([
            'status' => ['required', 'in:pending_approval,pending_payment,on_hold,complete,canceled,rejected'],
            'payment_method' => ['required', 'string', 'max:100'],
            'payment_status' => ['required', 'in:unpaid,pending,paid,refunded,partially_refunded'],
            'requires_approval' => ['nullable', 'boolean'],
            'items' => ['array'],
            'items.*.id' => ['required', 'integer'],
            'items.*.quantity' => ['required', 'integer', 'min:1'],
            'items.*.holder_name' => ['required', 'string', 'max:255'],
            'items.*.holder_email' => ['required', 'email', 'max:255'],
            'items.*.holder_phone' => ['nullable', 'string', 'max:255'],
        ]);

        $oldStatus = $order->status;
        $oldPaymentStatus = $order->payment_status;
        $oldPaymentMethod = $order->payment_method;

        $order->update([
            'status' => $validated['status'],
            'payment_method' => $validated['payment_method'],
            'payment_status' => $validated['payment_status'],
            'requires_approval' => (bool) ($validated['requires_approval'] ?? false),
            'approved_at' => $validated['status'] === 'pending_payment' ? ($order->approved_at ?? now()) : null,
        ]);

        $total = 0;
        $itemsInput = collect($validated['items'] ?? [])->keyBy('id');

        $order->load('items');
        foreach ($order->items as $item) {
            $updated = $itemsInput->get($item->id);
            if (! $updated) {
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

        $order->update(['total_amount' => $total]);

        activity('orders')
            ->performedOn($order)
            ->causedBy($request->user())
            ->withProperties([
                'from_status' => $oldStatus,
                'to_status' => $order->status,
                'from_payment_status' => $oldPaymentStatus,
                'to_payment_status' => $order->payment_status,
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

    public function storeNote(Request $request, Order $order)
    {
        $validated = $request->validate([
            'body' => ['required', 'string', 'max:2000'],
        ]);

        activity('order_notes')
            ->performedOn($order)
            ->causedBy($request->user())
            ->withProperties(['body' => $validated['body']])
            ->log('Order note added');

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

        Mail::raw(
            "Your order {$order->order_number} has been approved and is now waiting for payment.\n\nPay now: {$paymentLink}",
            static function ($message) use ($order) {
                $message->to($order->customer->email)
                    ->subject('Order approved - payment required');
            }
        );

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
            'payment_link_token' => null,
        ]);

        activity('orders')
            ->performedOn($order)
            ->causedBy($request->user())
            ->withProperties([
                'from_status' => $oldStatus,
                'to_status' => $order->status,
            ])
            ->log('Order rejected');

        return back()->with('success', 'Order rejected successfully.');
    }

}
