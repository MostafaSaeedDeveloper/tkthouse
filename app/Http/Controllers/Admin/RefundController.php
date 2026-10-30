<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Models\PaymentAttempt;
use App\Models\PaymentRefund;
use App\Orders\OrderAccess;
use App\Services\Payments\Refunds\RefundService;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

/** Every action re-checks permission, order visibility and (for agents) ownership on the server: hidden buttons are not the control. */
class RefundController extends Controller
{
    public function __construct(private readonly RefundService $refunds)
    {
    }

    private function visibleRefunds($user)
    {
        return PaymentRefund::query()->whereHas('order', fn ($o) => $o->visibleTo($user));
    }

    private function authorizeRefund(Request $request, PaymentRefund $refund, bool $manage = true): void
    {
        $user = $request->user();
        abort_unless($this->visibleRefunds($user)->whereKey($refund->id)->exists(), 404);
        abort_if($manage && ! OrderAccess::canManage($refund->order, $user), 403);
    }

    public function index(Request $request)
    {
        $user = $request->user();
        $refunds = $this->visibleRefunds($user)->with(['order', 'attempt:id,internal_reference,transaction_id', 'account:id,name'])
            ->when($request->filled('status'), fn ($q) => $q->where('status', $request->string('status')))
            ->when($request->filled('provider'), fn ($q) => $q->where('provider', $request->string('provider')))
            ->latest('id')->paginate(20)->withQueryString();

        return view('admin.payments.refunds', ['refunds' => $refunds, 'seeAccounts' => TransactionController::canSeeAccounts($user)]);
    }

    public function show(Request $request, PaymentRefund $refund)
    {
        $this->authorizeRefund($request, $refund, false);
        $refund->load(['order.customer', 'attempt', 'account', 'requester', 'approver', 'confirmer', 'submitter', 'ticketLinks.ticket']);
        $user = $request->user();

        return view('admin.payments.refund-show', [
            'refund' => $refund, 'seeAccounts' => TransactionController::canSeeAccounts($user),
            'canCreate' => $user->can('payments.refunds.create') && OrderAccess::canManage($refund->order, $user),
            'canApprove' => $user->can('payments.refunds.approve') && OrderAccess::canManage($refund->order, $user),
            'ticketChoices' => ($refund->status === PaymentRefund::SUCCEEDED && in_array($refund->effects_status, [PaymentRefund::EFFECTS_REVIEW, null], true)) ? $this->refunds->ticketChoices($refund->order) : collect(),
        ]);
    }

    /** Validation shared by the payment-attempt form and the legacy-order form. */
    private function validateRequest(Request $request): array
    {
        return $request->validate([
            'type' => ['required', 'in:full,partial,void'],
            'amount' => ['nullable', 'string', 'max:14'],
            'reason' => ['required', 'string', 'min:5', 'max:300'],
            'idempotency_key' => ['required', 'string', 'max:64'],
            'confirm' => ['accepted'],
            'tickets' => ['nullable', 'array', 'max:500'],
            'tickets.*' => ['integer'],
            'no_tickets' => ['nullable', 'boolean'],
            'external_method' => ['nullable', 'string', 'max:30'],
            'internal_notes' => ['nullable', 'string', 'max:2000'],
            'provider_reference' => ['nullable', 'string', 'max:120'],
            'refunded_at' => ['nullable', 'date', 'before_or_equal:today'],
        ], ['confirm.accepted' => 'Please confirm that you understand this is a financial operation.']);
    }

    private function options(array $data): array
    {
        return [
            'tickets' => $data['tickets'] ?? [], 'no_tickets' => (bool) ($data['no_tickets'] ?? false), 'external_method' => $data['external_method'] ?? null,
            'internal_notes' => $data['internal_notes'] ?? null, 'provider_reference' => $data['provider_reference'] ?? null, 'refunded_at' => $data['refunded_at'] ?? null,
        ];
    }

    public function store(Request $request, PaymentAttempt $attempt)
    {
        $user = $request->user();
        abort_unless(TransactionController::visible($user)->whereKey($attempt->id)->exists(), 404);
        abort_unless(OrderAccess::canManage($attempt->order, $user), 403);

        $data = $this->validateRequest($request);

        try {
            $refund = $this->refunds->request($attempt, $user, $data['type'], $data['amount'] ?? null, $data['reason'], $data['idempotency_key'], $this->options($data));
        } catch (ValidationException $e) {
            return back()->withErrors($e->errors())->withInput();
        }

        return $this->afterRequest($refund);
    }

    /** Legacy paid order (no payment attempt): the manual refund is recorded against the order itself. */
    public function storeForOrder(Request $request, Order $order)
    {
        $user = $request->user();
        abort_unless(Order::query()->visibleTo($user)->whereKey($order->id)->exists(), 404);
        abort_unless(OrderAccess::canManage($order, $user), 403);

        $data = $this->validateRequest($request);
        if (! $this->refunds->isLegacyOrder($order)) {
            return back()->withErrors(['refund' => 'This order has an online payment: refund that payment instead.'])->withInput();
        }

        try {
            $refund = $this->refunds->requestForOrder($order, $user, $data['type'], $data['amount'] ?? null, $data['reason'], $data['idempotency_key'], $this->options($data));
        } catch (ValidationException $e) {
            return back()->withErrors($e->errors())->withInput();
        }

        return $this->afterRequest($refund);
    }

    private function afterRequest(PaymentRefund $refund)
    {
        $msg = match (true) {
            $refund->awaiting_approval => 'Refund requested; it waits for approval before anything is sent.',
            $refund->mode === 'manual' => 'Manual refund recorded as requested. Carry it out with the provider, then confirm it here. Tickets stay valid until the refund is confirmed.',
            $refund->status === PaymentRefund::UNKNOWN => 'The refund request may or may not have reached the provider. Do NOT repeat it: check the status first.',
            $refund->status === PaymentRefund::REJECTED => 'The provider rejected the refund: '.$refund->failure_reason,
            $refund->status === PaymentRefund::SUCCEEDED => 'Refund confirmed by the provider.',
            default => 'Refund sent to the provider. It is not complete until the provider confirms it.',
        };

        return redirect()->route('admin.payments.refunds.show', $refund)->with($refund->status === PaymentRefund::REJECTED ? 'error' : 'success', $msg);
    }

    public function check(Request $request, PaymentRefund $refund)
    {
        $this->authorizeRefund($request, $refund);
        $fresh = $this->refunds->inquire($refund);

        return back()->with('success', 'Checked with the provider: refund is '.$fresh->status.'.');
    }

    public function approve(Request $request, PaymentRefund $refund)
    {
        $this->authorizeRefund($request, $refund);
        $data = $request->validate(['note' => ['nullable', 'string', 'max:300']]);

        return $this->guard(fn () => $this->refunds->approve($refund, $request->user(), $data['note'] ?? null), 'Refund approved.');
    }

    public function reject(Request $request, PaymentRefund $refund)
    {
        $this->authorizeRefund($request, $refund);
        $data = $request->validate(['note' => ['required', 'string', 'min:5', 'max:300']]);

        return $this->guard(fn () => $this->refunds->reject($refund, $request->user(), $data['note']), 'Refund request rejected.');
    }

    public function submitted(Request $request, PaymentRefund $refund)
    {
        $this->authorizeRefund($request, $refund);

        return $this->guard(fn () => $this->refunds->markSubmitted($refund, $request->user()), 'Marked as submitted to the provider.');
    }

    public function confirm(Request $request, PaymentRefund $refund)
    {
        $this->authorizeRefund($request, $refund);
        $data = $request->validate([
            'provider_reference' => ['required', 'string', 'max:120'], 'note' => ['required', 'string', 'min:5', 'max:300'],
            'refunded_at' => ['required', 'date', 'before_or_equal:today'], 'external_method' => ['nullable', 'string', 'max:30'],
        ]);

        return $this->guard(fn () => $this->refunds->confirmSucceeded($refund, $request->user(), $data['provider_reference'], $data['note'], $data['refunded_at'], $data['external_method'] ?? null), 'Refund recorded as completed. Its tickets were canceled and the order updated.');
    }

    public function fail(Request $request, PaymentRefund $refund)
    {
        $this->authorizeRefund($request, $refund);
        $data = $request->validate(['note' => ['required', 'string', 'min:5', 'max:300']]);

        return $this->guard(fn () => $this->refunds->confirmFailed($refund, $request->user(), $data['note']), 'Refund recorded as failed. The amount is available again.');
    }

    /** Ticket cancellation for a confirmed refund that could not be matched automatically (needs the approve permission). */
    public function tickets(Request $request, PaymentRefund $refund)
    {
        $this->authorizeRefund($request, $refund);
        $data = $request->validate(['tickets' => ['required', 'array', 'min:1', 'max:500'], 'tickets.*' => ['integer']]);

        return $this->guard(fn () => $this->refunds->resolveTickets($refund, $request->user(), $data['tickets']), 'The selected tickets were canceled.');
    }

    private function guard(\Closure $action, string $ok)
    {
        try {
            $action();
        } catch (ValidationException $e) {
            return back()->withErrors($e->errors());
        }

        return back()->with('success', $ok);
    }
}
