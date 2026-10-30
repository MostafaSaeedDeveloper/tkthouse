<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Models\PaymentAttempt;
use App\Models\PaymentMethod;
use App\Support\PaymentEnvironment;
use Illuminate\Http\Request;

/**
 * Read-only payments overview built from the real payment attempts and orders. There is deliberately no refund or
 * settlement action here: those need provider-side logic and their own permissions.
 */
class PaymentController extends Controller
{
    private const FILTERS = ['all' => 'All attempts', 'paid' => 'Paid', 'initiated' => 'Processing', 'failed' => 'Failed', 'expired' => 'Expired', 'review' => 'Needs review'];

    public function index(Request $request)
    {
        $user = $request->user();
        $filter = array_key_exists((string) $request->query('status'), self::FILTERS) ? (string) $request->query('status') : 'all';
        $provider = trim((string) $request->query('provider', ''));
        $search = trim((string) $request->query('q', ''));

        // Hidden (statistics-excluded) orders need the showing_orders permission here too.
        $base = PaymentAttempt::query()->whereHas('order', fn ($o) => $o->visibleTo($user));

        $stats = [
            'paid' => (clone $base)->where('status', 'paid')->sum('amount'),
            'paid_count' => (clone $base)->where('status', 'paid')->count(),
            'processing' => (clone $base)->where('status', 'initiated')->count(),
            'failed' => (clone $base)->where('status', 'failed')->count(),
            'review' => (clone $base)->where('meta->needs_review', true)->count(),
            'refunded' => Order::query()->visibleTo($user)->withConfirmedRefund()->count(), // orders with a CONFIRMED refund, not orders someone edited to "refunded"
        ];

        $attempts = (clone $base)->with(['order.customer', 'failedWebhookEvent'])
            ->when($filter === 'review', fn ($q) => $q->whereNotNull('meta->needs_review'))
            ->when(! in_array($filter, ['all', 'review'], true), fn ($q) => $q->where('status', $filter))
            ->when($provider !== '', fn ($q) => $q->where('provider', $provider))
            ->when($search !== '', function ($q) use ($search) {
                $like = '%'.str_replace(['%', '_'], ['\%', '\_'], $search).'%';
                $q->where(fn ($w) => $w->where('reference', 'like', $like)->orWhere('transaction_id', 'like', $like)
                    ->orWhereHas('order', fn ($o) => $o->where('order_number', 'like', $like)));
            })
            ->latest('id')->paginate(15)->withQueryString();

        $from = $request->filled('from') ? \Illuminate\Support\Carbon::parse($request->string('from'))->startOfDay() : now()->startOfMonth();
        $to = $request->filled('to') ? \Illuminate\Support\Carbon::parse($request->string('to'))->endOfDay() : now()->endOfDay();
        $seeAccounts = TransactionController::canSeeAccounts($user);
        $reports = app(\App\Services\Payments\PaymentReport::class);
        $report = $reports->build($user, $from, $to, $seeAccounts);
        $previous = $reports->previous($user, $from, $to);

        return view('admin.payments.index', [
            'report' => $report, 'previous' => $previous, 'from' => $from, 'to' => $to, 'seeAccounts' => $seeAccounts,
            'accountNames' => $seeAccounts ? \App\Models\IntegrationAccount::query()->pluck('name', 'id') : collect(),
            'methodNames' => PaymentMethod::query()->pluck('name', 'code'),
            'attempts' => $attempts,
            'stats' => $stats,
            'filter' => $filter,
            'filters' => self::FILTERS,
            'provider' => $provider,
            'providers' => PaymentAttempt::query()->distinct()->orderBy('provider')->pluck('provider'),
            'search' => $search,
            'methods' => PaymentMethod::query()->orderBy('name')->get(['code', 'name', 'provider', 'is_active']),
            'liveGateways' => PaymentEnvironment::liveGatewaysAllowed(),
            'fakeAllowed' => PaymentEnvironment::fakeGatewayAllowed(),
        ]);
    }
}
