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
        $seeAccounts = self::canSeeAccounts($user);

        $attempts = self::visible($user)->with(['order.customer', 'integrationAccount:id,name,provider'])
            ->when($status !== '', fn ($w) => $w->where('status', $status))
            ->when($provider !== '', fn ($w) => $w->where('provider', $provider))
            ->when($seeAccounts && $accountId > 0, fn ($w) => $w->where('integration_account_id', $accountId))
            ->when($request->filled('from'), fn ($w) => $w->whereDate('created_at', '>=', $request->string('from')))
            ->when($request->filled('to'), fn ($w) => $w->whereDate('created_at', '<=', $request->string('to')))
            ->when($q !== '', function ($w) use ($q) {
                $like = '%'.str_replace(['%', '_'], ['\%', '\_'], $q).'%';
                $w->where(fn ($x) => $x->where('internal_reference', 'like', $like)->orWhere('reference', 'like', $like)->orWhere('transaction_id', 'like', $like)
                    ->orWhere('provider_order_id', 'like', $like)->orWhere('provider_reference', 'like', $like)
                    ->orWhereHas('order', fn ($o) => $o->where('order_number', 'like', $like)));
            })
            ->latest('id')->paginate(20)->withQueryString();

        return view('admin.payments.transactions', [
            'attempts' => $attempts, 'seeAccounts' => $seeAccounts,
            'accounts' => $seeAccounts ? IntegrationAccount::query()->whereIn('provider', ['paymob', 'fawaterak', 'fake'])->orderBy('name')->get(['id', 'name']) : collect(),
            'providers' => PaymentAttempt::query()->distinct()->orderBy('provider')->pluck('provider'),
            'filters' => compact('q', 'status', 'provider', 'accountId'),
            'canInquire' => $user->can('payments.transactions.inquire'),
        ]);
    }

    public function show(Request $request, PaymentAttempt $attempt)
    {
        $user = $request->user();
        abort_unless(self::visible($user)->whereKey($attempt->id)->exists(), 404);
        $attempt->load(['order.customer', 'integrationAccount', 'webhookEvents' => fn ($q) => $q->latest('id')]);

        return view('admin.payments.transaction-show', [
            'attempt' => $attempt, 'seeAccounts' => self::canSeeAccounts($user), 'canInquire' => $user->can('payments.transactions.inquire'),
            'refunds' => class_exists(\App\Models\PaymentRefund::class) ? \App\Models\PaymentRefund::query()->where('payment_attempt_id', $attempt->id)->latest('id')->get() : collect(),
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
}
