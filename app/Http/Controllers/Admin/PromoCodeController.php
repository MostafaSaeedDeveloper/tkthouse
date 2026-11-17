<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Orders\OrderAccess;
use App\Models\PromoCode;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Spatie\Activitylog\Models\Activity;

class PromoCodeController extends Controller
{
    public function index()
    {
        $promoCodes = PromoCode::query()->latest()->paginate(20);

        // Sales numbers are for Super Admin only; nothing is computed for anyone else.
        $showStats = OrderAccess::isSuper(auth()->user());
        $perCode = collect();
        $totals = null;

        if ($showStats) {
            // Sales numbers follow the dashboard rules: captured (paid) orders only, never the ones excluded from statistics,
            // revenue = tickets minus the discount (checkout fees are not part of it).
            $perCode = $this->salesQuery()
                ->selectRaw('promo_code_id, COUNT(*) AS orders, COALESCE(SUM(discount_amount), 0) AS discount, COALESCE(SUM('.self::NET.'), 0) AS revenue')
                ->groupBy('promo_code_id')
                ->get()
                ->keyBy('promo_code_id');

            $totals = [
                'codes' => PromoCode::count(),
                'active' => PromoCode::where('is_active', true)->count(),
                'redemptions' => (int) PromoCode::sum('used_count'),
                'orders' => (int) $perCode->sum('orders'),
                'discount' => (float) $perCode->sum('discount'),
                'revenue' => (float) $perCode->sum('revenue'),
            ];
        }

        $activities = auth()->user()->can('activity-logs.view')
            ? Activity::with('causer')->where('log_name', 'promo_codes')->latest()->limit(10)->get()
            : null;

        return view('admin/settings/promo-codes/index', compact('promoCodes', 'perCode', 'totals', 'showStats', 'activities'));
    }

    private const NET = 'CASE WHEN subtotal_amount > 0 THEN subtotal_amount - COALESCE(discount_amount, 0) ELSE total_amount END';

    private function salesQuery()
    {
        return Order::query()->captured()->where('exclude_from_statistics', false)->whereNotNull('promo_code_id');
    }

    public function create()
    {
        return view('admin/settings/promo-codes/form', ['promoCode' => new PromoCode()]);
    }

    public function store(Request $request)
    {
        PromoCode::create($this->validateRequest($request));

        return redirect()->route('admin.promo-codes.index')->with('success', 'Promo code created successfully.');
    }

    public function edit(PromoCode $promoCode)
    {
        return view('admin/settings/promo-codes/form', compact('promoCode'));
    }

    public function update(Request $request, PromoCode $promoCode)
    {
        $promoCode->update($this->validateRequest($request, $promoCode));

        return redirect()->route('admin.promo-codes.index')->with('success', 'Promo code updated successfully.');
    }

    public function destroy(PromoCode $promoCode)
    {
        $promoCode->delete();

        return back()->with('success', 'Promo code deleted successfully.');
    }

    private function validateRequest(Request $request, ?PromoCode $promoCode = null): array
    {
        $validated = $request->validate([
            'code' => ['required', 'string', 'max:50', Rule::unique('promo_codes', 'code')->ignore($promoCode?->id)],
            'discount_type' => ['required', Rule::in(['percent', 'fixed'])],
            'discount_value' => ['required', 'numeric', 'min:0.01'],
            'usage_limit' => ['nullable', 'integer', 'min:1'],
            'starts_at' => ['nullable', 'date'],
            'ends_at' => ['nullable', 'date', 'after_or_equal:starts_at'],
            'is_active' => ['nullable', 'boolean'],
        ]);

        return [
            'code' => strtoupper(trim($validated['code'])),
            'discount_type' => $validated['discount_type'],
            'discount_value' => $validated['discount_value'],
            'usage_limit' => $validated['usage_limit'] ?? null,
            'starts_at' => $validated['starts_at'] ?? null,
            'ends_at' => $validated['ends_at'] ?? null,
            'is_active' => $request->boolean('is_active'),
        ];
    }
}
