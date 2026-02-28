<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Str;

class AffiliateController extends Controller
{
    public function index()
    {
        $affiliates = User::query()
            ->withCount('referredUsers')
            ->withCount('affiliateOrders')
            ->withSum([
                'affiliateOrders as affiliate_paid_revenue' => fn (Builder $query) => $query->where('payment_status', 'paid'),
            ], 'total_amount')
            ->orderByDesc('affiliate_paid_revenue')
            ->orderByDesc('affiliate_orders_count')
            ->paginate(20);

        return view('admin.affiliates.index', [
            'affiliates' => $affiliates,
        ]);
    }

    public function show(User $affiliate)
    {
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
        ]);
    }

    public function generateLink(User $affiliate): RedirectResponse
    {
        $affiliate->update([
            'affiliate_code' => $this->uniqueAffiliateCode(),
        ]);

        return back()->with('success', 'Affiliate link has been generated successfully.');
    }

    private function uniqueAffiliateCode(): string
    {
        do {
            $code = Str::upper(Str::random(8));
        } while (User::query()->where('affiliate_code', $code)->exists());

        return $code;
    }
}
