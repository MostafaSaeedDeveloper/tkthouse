<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
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
        $refund->load(['order.customer', 'attempt', 'account', 'requester']);
        $user = $request->user();

        return view('admin.payments.refund-show', [
            'refund' => $refund, 'seeAccounts' => TransactionController::canSeeAccounts($user),
            'canCreate' => $user->can('payments.refunds.create') && OrderAccess::canManage($refund->order, $user),
            'canApprove' => $user->can('payments.refunds.approve') && OrderAccess::canManage($refund->order, $user),
        ]);
    }

    public function store(Request $request, PaymentAttempt $attempt)
    {
        $user = $request->user();
        abort_unless(TransactionController::visible($user)->whereKey($attempt->id)->exists(), 404);
        abort_unless(OrderAccess::canManage($attempt->order, $user), 403);

        $data = $request->validate([
            'type' => ['required', 'in:full,partial,void'],
            'amount' => ['nullable', 'string', 'max:14'],
            'reason' => ['required', 'string', 'min:5', 'max:300'],
            'idempotency_key' => ['required', 'string', 'max:64'],
            'confirm' => ['accepted'],
        ], ['confirm.accepted' => 'Please confirm that you understand this is a financial operation.']);

        try {
            $refund = $this->refunds->request($attempt, $user, $data['type'], $data['amount'] ?? null, $data['reason'], $data['idempotency_key']);
        } catch (ValidationException $e) {
            return back()->withErrors($e->errors())->withInput();
        }

        $msg = match (true) {
            $refund->awaiting_approval => 'Refund requested; it waits for approval before anything is sent.',
            $refund->mode === 'manual' => 'Manual refund recorded as requested. Carry it out with the provider, then confirm it here.',
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

        return $this->guard(fn () => $this->refunds->approve($refund, $request->user()), 'Refund approved.');
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
        $data = $request->validate(['provider_reference' => ['required', 'string', 'max:120'], 'note' => ['required', 'string', 'min:5', 'max:300']]);

        return $this->guard(fn () => $this->refunds->confirmSucceeded($refund, $request->user(), $data['provider_reference'], $data['note']), 'Refund recorded as completed.');
    }

    public function fail(Request $request, PaymentRefund $refund)
    {
        $this->authorizeRefund($request, $refund);
        $data = $request->validate(['note' => ['required', 'string', 'min:5', 'max:300']]);

        return $this->guard(fn () => $this->refunds->confirmFailed($refund, $request->user(), $data['note']), 'Refund recorded as failed. The amount is available again.');
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
