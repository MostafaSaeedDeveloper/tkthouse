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


    public function create()
    {
        return view('admin.affiliates.create', [
            'customers' => User::query()
                ->orderBy('name')
                ->get(['id', 'name', 'email', 'affiliate_code']),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'user_id' => ['required', 'integer', 'exists:users,id'],
        ]);

        $affiliate = User::query()->findOrFail((int) $validated['user_id']);

        $affiliate->update([
            'affiliate_code' => $this->uniqueAffiliateCode(),
        ]);

        return redirect()->route('admin.affiliates.show', $affiliate)
            ->with('success', 'Affiliate link has been generated successfully.');
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

    private function uniqueAffiliateCode(): string
    {
        do {
            $code = Str::upper(Str::random(8));
        } while (User::query()->where('affiliate_code', $code)->exists());

        return $code;
    }
}
