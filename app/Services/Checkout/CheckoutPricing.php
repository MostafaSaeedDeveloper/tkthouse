<?php

namespace App\Services\Checkout;

use App\Models\Event;
use App\Models\PromoCode;
use Illuminate\Support\Collection;
use Illuminate\Validation\ValidationException;

/**
 * Single pricing rule used by the review page, the promo preview and order creation:
 *   discount = promo applied to the ticket subtotal (never more than the subtotal)
 *   fees     = event fees calculated on the UNDISCOUNTED subtotal (percentage) or fixed per order
 *   total    = (subtotal - discount) + fees
 */
class CheckoutPricing
{
    /**
     * @param Collection<int, array<string, mixed>> $units
     * @param bool $lockPromo true inside the order transaction: locks the promo row so its usage limit holds under concurrency
     * @throws ValidationException (promo_code) when a promo code was supplied and is not valid
     */
    public function quote(Event $event, Collection $units, ?string $promoCode = null, bool $lockPromo = false): Quote
    {
        $subtotal = round((float) $units->sum(fn ($unit) => (float) $unit['ticket_price']), 2);

        $promo = null;
        $discount = 0.0;
        $code = strtoupper(trim((string) $promoCode));
        if ($code !== '') {
            [$promo, $discount] = $this->evaluatePromo($code, $subtotal, $lockPromo);
        }

        $fees = $this->fees($event, $subtotal);
        $feesTotal = round(array_sum(array_column($fees, 'amount')), 2);
        $total = round(max(0, $subtotal - $discount) + $feesTotal, 2);

        $lines = $units
            ->groupBy(fn ($unit) => (int) $unit['ticket_id'])
            ->map(fn (Collection $rows) => [
                'name' => (string) $rows->first()['ticket_name'],
                'count' => $rows->count(),
                'unit_price' => (float) $rows->first()['ticket_price'],
                'total' => round((float) $rows->sum('ticket_price'), 2),
                'is_couple' => (bool) ($rows->first()['is_couple'] ?? false),
            ])
            ->values()
            ->all();

        return new Quote($subtotal, $discount, $fees, $feesTotal, $total, $lines, $units->count(), $promo);
    }

    /** @return array<int, array{name: string, fee_type: string, value: float, amount: float}> */
    public function fees(Event $event, float $subtotal): array
    {
        return $event->loadMissing('fees')->fees
            ->map(function ($fee) use ($subtotal) {
                $value = (float) ($fee->value ?? 0);
                $amount = $fee->fee_type === 'percentage' ? round(($subtotal * $value) / 100, 2) : round($value, 2);

                return ['name' => (string) $fee->name, 'fee_type' => (string) $fee->fee_type, 'value' => $value, 'amount' => max(0, $amount)];
            })
            ->filter(fn (array $fee) => $fee['amount'] > 0)
            ->values()
            ->all();
    }

    /** @return array{0: PromoCode, 1: float} */
    private function evaluatePromo(string $code, float $subtotal, bool $lock): array
    {
        $query = PromoCode::query()->where('code', $code);
        $promo = $lock ? $query->lockForUpdate()->first() : $query->first();

        if (! $promo || ! $promo->is_active) {
            throw ValidationException::withMessages(['promo_code' => 'Promo code is invalid.']);
        }
        if ($promo->starts_at && now()->lt($promo->starts_at)) {
            throw ValidationException::withMessages(['promo_code' => 'Promo code is not active yet.']);
        }
        if ($promo->ends_at && now()->gt($promo->ends_at)) {
            throw ValidationException::withMessages(['promo_code' => 'Promo code has expired.']);
        }
        if ($promo->usage_limit !== null && $promo->used_count >= $promo->usage_limit) {
            throw ValidationException::withMessages(['promo_code' => 'Promo code usage limit reached.']);
        }

        $discount = $promo->discount_type === 'percent'
            ? round(($subtotal * (float) $promo->discount_value) / 100, 2)
            : round((float) $promo->discount_value, 2);

        return [$promo, min($discount, $subtotal)];
    }
}
