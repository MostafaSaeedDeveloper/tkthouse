<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\IntegrationAccount;
use App\Models\PaymentAttempt;
use App\Orders\OrderAccess;
use App\Services\Payments\PaymentService;
use Illuminate\Http\Request;

/** Payment transactions (= payment attempts) with every reference we hold. Scoped by the same order-visibility rules as orders. */
class TransactionController extends Controller
{
    public static function canSeeAccounts($user): bool
    {
        return $user->can('payments.accounts.view') || $user->can('settings.payment-accounts.view') || OrderAccess::isSuper($user);
    }

    public static function visible($user)
    {
        return PaymentAttempt::query()->whereHas('order', fn ($o) => $o->visibleTo($user));
    }

    public function index(Request $request)
    {
        $user = $request->user();
        $q = trim((string) $request->query('q', ''));
        $status = (string) $request->query('status', '');
        $provider = (string) $request->query('provider', '');
        $accountId = (int) $request->query('account', 0);
        $method = trim((string) $request->query('pm', ''));
        $txn = trim((string) $request->query('txn', ''));
        $orderRef = trim((string) $request->query('order', ''));
        $seeAccounts = self::canSeeAccounts($user);
        $esc = fn (string $v) => '%'.str_replace(['%', '_'], ['\%', '\_'], $v).'%';

        $attempts = self::visible($user)->with(['order.customer', 'integrationAccount:id,name,provider', 'failedWebhookEvent'])
            ->when($status !== '', fn ($w) => $w->where('status', $status))
            ->when($provider !== '', fn ($w) => $w->where('provider', $provider))
            ->when($seeAccounts && $accountId > 0, fn ($w) => $w->where('integration_account_id', $accountId))
            ->when($method !== '', fn ($w) => $w->where('method_code', $method))
            ->when($txn !== '', fn ($w) => $w->where(fn ($x) => $x->where('transaction_id', 'like', $esc($txn))->orWhere('provider_order_id', 'like', $esc($txn))->orWhere('provider_reference', 'like', $esc($txn))))
            ->when($orderRef !== '', fn ($w) => $w->whereHas('order', fn ($o) => $o->where('order_number', 'like', $esc($orderRef))))
            ->when($request->filled('from'), fn ($w) => $w->whereDate('created_at', '>=', $request->string('from')))
            ->when($request->filled('to'), fn ($w) => $w->whereDate('created_at', '<=', $request->string('to')))
            ->when($q !== '', function ($w) use ($q) {
                $like = '%'.str_replace(['%', '_'], ['\%', '\_'], $q).'%';
                $w->where(fn ($x) => $x->where('internal_reference', 'like', $like)->orWhere('reference', 'like', $like)->orWhere('transaction_id', 'like', $like)
                    ->orWhere('provider_order_id', 'like', $like)->orWhere('provider_reference', 'like', $like)
                    ->orWhereHas('order', fn ($o) => $o->where('order_number', 'like', $like)));
            })
            ->latest('id')->paginate(20)->withQueryString();

        // refund state per attempt (latest refund + refunded total), for the list column
        $ids = $attempts->getCollection()->pluck('id');
        $refunds = \App\Models\PaymentRefund::query()->whereIn('payment_attempt_id', $ids)->orderBy('id')->get(['payment_attempt_id', 'status', 'amount_minor'])->groupBy('payment_attempt_id');

        return view('admin.payments.transactions', [
            'refunds' => $refunds, 'methods' => PaymentAttempt::query()->distinct()->orderBy('method_code')->pluck('method_code'),
            'attempts' => $attempts, 'seeAccounts' => $seeAccounts,
            'accounts' => $seeAccounts ? IntegrationAccount::query()->whereIn('provider', ['paymob', 'fawaterak', 'fake'])->orderBy('name')->get(['id', 'name']) : collect(),
            'providers' => PaymentAttempt::query()->distinct()->orderBy('provider')->pluck('provider'),
            'filters' => compact('q', 'status', 'provider', 'accountId', 'method', 'txn', 'orderRef'),
            'canInquire' => $user->can('payments.transactions.inquire'),
        ]);
    }

    public function show(Request $request, PaymentAttempt $attempt)
    {
        $user = $request->user();
        abort_unless(self::visible($user)->whereKey($attempt->id)->exists(), 404);
        $attempt->load(['order.customer', 'integrationAccount', 'webhookEvents' => fn ($q) => $q->latest('id')]);
        $refunds = \App\Models\PaymentRefund::query()->where('payment_attempt_id', $attempt->id)->latest('id')->get();
        $siblings = PaymentAttempt::query()->where('order_id', $attempt->order_id)->where('id', '!=', $attempt->id)->orderBy('id')->get();
        $canSeeAcc = self::canSeeAccounts($user);
        // provider records (lookup/CSV) that match this payment exactly: visible only to staff who may see accounts
        $external = $canSeeAcc ? \App\Models\ExternalTransaction::query()->where('matched_attempt_id', $attempt->id)->get() : collect();

        // one timeline from what we actually hold; the providers offer no history API for Paymob, so this is OUR record of events
        $history = collect([['at' => $attempt->created_at, 'label' => 'Payment session opened', 'detail' => $attempt->method_code]]);
        foreach ($attempt->webhookEvents as $e) {
            $history->push(['at' => $e->received_at, 'label' => 'Webhook: '.$e->event_type, 'detail' => ($e->signatureLabel() ?: '—').', '.($e->processing_status ?? '—')]);
        }
        if ($attempt->paid_at ?? $attempt->settled_at) {
            $history->push(['at' => $attempt->paid_at ?? $attempt->settled_at, 'label' => 'Paid', 'detail' => $attempt->transaction_id ? 'transaction '.$attempt->transaction_id : null]);
        }
        if ($attempt->status === 'failed' && $attempt->failure_reason) {
            $history->push(['at' => $attempt->updated_at, 'label' => 'Failed', 'detail' => $attempt->failure_reason]);
        }
        foreach ($refunds as $r) {
            foreach ((array) data_get($r->meta, 'timeline', []) as $t) {
                $history->push(['at' => \Illuminate\Support\Carbon::parse($t['at'] ?? $r->requested_at), 'label' => 'Refund '.$r->internal_reference.': '.str_replace('_', ' ', $t['event'] ?? ''), 'detail' => $t['note'] ?? null]);
            }
        }
        if ($attempt->last_checked_at) {
            $history->push(['at' => $attempt->last_checked_at, 'label' => 'Last checked with the provider', 'detail' => null]);
        }

        return view('admin.payments.transaction-show', [
            'attempt' => $attempt, 'seeAccounts' => $canSeeAcc, 'canInquire' => $user->can('payments.transactions.inquire'),
            'refunds' => $refunds, 'siblings' => $siblings, 'external' => $external, 'history' => $history->sortBy(fn ($h) => $h['at']?->timestamp ?? 0)->values(),
            'capability' => app(\App\Services\Payments\Refunds\RefundService::class)->capability($attempt),
        ]);
    }

    /** "Check payment status": asks the attempt's own gateway/account; the settlement rules (amount, currency, ownership, one-time tickets) apply unchanged. */
    public function inquire(Request $request, PaymentAttempt $attempt, PaymentService $payments)
    {
        abort_unless(self::visible($request->user())->whereKey($attempt->id)->exists(), 404);
        abort_unless(OrderAccess::canManage($attempt->order, $request->user()), 403);

        try {
            $outcome = $payments->refreshAttempt($attempt);
        } catch (\Throwable $e) {
            return back()->with('error', 'The gateway could not be reached; nothing was changed ('.class_basename($e).').');
        }
        activity('orders')->performedOn($attempt->order)->causedBy($request->user())->withProperties(['attempt_id' => $attempt->id, 'outcome' => $outcome])->log('Payment status checked');

        return back()->with('success', 'Checked with the gateway: '.str_replace('_', ' ', $outcome).'.');
    }

    /**
     * "Mark as reviewed": clears the Needs review flag once someone has looked at the payment. Only the flag changes (never the
     * payment, the order or any money); who cleared it and what the flag said are kept in the attempt and the activity log.
     */
    public function dismissReview(Request $request, PaymentAttempt $attempt)
    {
        abort_unless(self::visible($request->user())->whereKey($attempt->id)->exists(), 404);
        abort_unless(OrderAccess::canManage($attempt->order, $request->user()), 403);

        $meta = $attempt->meta ?? [];
        $reason = $meta['needs_review'] ?? null;
        if (! $reason) {
            return back()->with('success', 'This payment was not flagged.');
        }

        unset($meta['needs_review'], $meta['flagged_at']);
        $meta['review_dismissed'] = ['reason' => $reason, 'by' => $request->user()->name, 'by_id' => $request->user()->id, 'at' => now()->toIso8601String()];
        $attempt->forceFill(['meta' => $meta])->saveQuietly();
        activity('orders')->performedOn($attempt->order)->causedBy($request->user())->withProperties(['attempt_id' => $attempt->id, 'reason' => $reason])->log('Payment review dismissed');

        return back()->with('success', 'Marked as reviewed.');
    }
}
