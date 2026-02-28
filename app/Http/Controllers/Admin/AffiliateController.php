<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class AffiliateController extends Controller
{
    public function index()
    {
        $affiliates = User::query()
            ->whereNotNull('affiliate_code')
            ->withCount('referredUsers')
            ->withCount('affiliateOrders')
            ->withSum([
                'affiliateOrders as affiliate_paid_revenue' => fn (Builder $query) => $query->where('payment_status', 'paid'),
            ], 'total_amount')
            ->orderByDesc('affiliate_paid_revenue')
            ->orderByDesc('affiliate_orders_count')
            ->paginate(20)
            ->through(function (User $affiliate) {
                $affiliate->generated_affiliate_link = $this->buildAffiliateLink($affiliate);

                return $affiliate;
            });

        return view('admin.affiliates.index', [
            'affiliates' => $affiliates,
        ]);
    }

    public function create()
    {
        return view('admin.affiliates.create', [
            'customers' => User::query()
                ->orderBy('name')
                ->get(['id', 'name', 'email', 'affiliate_code', 'affiliate_target_url']),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'user_id' => ['required', 'integer', 'exists:users,id'],
            'target_url' => ['nullable', 'string', 'max:2000'],
        ]);

        $affiliate = User::query()->findOrFail((int) $validated['user_id']);

        $targetUrl = $this->normalizeTargetUrl((string) ($validated['target_url'] ?? ''), $affiliate->affiliate_target_url);

        $affiliate->update([
            'affiliate_code' => $affiliate->affiliate_code ?: $this->uniqueAffiliateCode(),
            'affiliate_target_url' => $targetUrl,
        ]);

        return redirect()->route('admin.affiliates.show', $affiliate)
            ->with('success', 'Affiliate link has been generated successfully.');
    }

    public function show(User $affiliate)
    {
        abort_if(! $affiliate->affiliate_code, 404);

        $affiliate->loadCount(['referredUsers', 'affiliateOrders']);

        $orders = $affiliate->affiliateOrders()
            ->with(['customer', 'user'])
            ->latest()
            ->paginate(15);

        $referredUsers = $affiliate->referredUsers()
            ->latest()
            ->paginate(15, ['*'], 'users_page');

        $stats = [
            'orders_total' => (int) $affiliate->affiliateOrders_count,
            'orders_paid' => (int) $affiliate->affiliateOrders()->where('payment_status', 'paid')->count(),
            'revenue_paid' => (float) $affiliate->affiliateOrders()->where('payment_status', 'paid')->sum('total_amount'),
            'referred_users' => (int) $affiliate->referredUsers_count,
        ];

        return view('admin.affiliates.show', [
            'affiliate' => $affiliate,
            'orders' => $orders,
            'referredUsers' => $referredUsers,
            'stats' => $stats,
            'affiliateLink' => $this->buildAffiliateLink($affiliate),
        ]);
    }

    private function buildAffiliateLink(User $affiliate): ?string
    {
        if (! $affiliate->affiliate_code) {
            return null;
        }

        $targetUrl = $this->normalizeTargetUrl((string) ($affiliate->affiliate_target_url ?? ''), '/account/register');

        return (string) url($targetUrl.(str_contains($targetUrl, '?') ? '&' : '?').'ref='.$affiliate->affiliate_code);
    }

    private function normalizeTargetUrl(string $candidate, ?string $fallback = null): string
    {
        $candidate = trim($candidate);
        $fallback = trim((string) $fallback);

        if ($candidate === '') {
            return $fallback !== '' ? $fallback : '/account/register';
        }

        if (str_starts_with($candidate, url('/'))) {
            $path = '/'.ltrim((string) parse_url($candidate, PHP_URL_PATH), '/');
            $query = (string) parse_url($candidate, PHP_URL_QUERY);

            return $query !== '' ? $path.'?'.$query : $path;
        }

        if (str_starts_with($candidate, '/')) {
            return $candidate;
        }

        return '/'.ltrim($candidate, '/');
    }

    private function uniqueAffiliateCode(): string
    {
        do {
            $code = Str::upper(Str::random(8));
        } while (User::query()->where('affiliate_code', $code)->exists());

        return $code;
    }
}
