<?php

namespace App\Services\Checkout;

use App\Models\PromoCode;

/** A priced checkout. Computed on the server; the browser only ever displays it. */
final class Quote
{
    /**
     * @param array<int, array{name: string, fee_type: string, value: float, amount: float}> $fees
     * @param array<int, array{name: string, count: int, unit_price: float, total: float, is_couple: bool}> $lines
     */
    public function __construct(
        public readonly float $subtotal,
        public readonly float $discount,
        public readonly array $fees,
        public readonly float $feesTotal,
        public readonly float $total,
        public readonly array $lines,
        public readonly int $people,
        public readonly ?PromoCode $promo = null,
    ) {
    }

    public function toArray(): array
    {
        return [
            'subtotal' => $this->subtotal,
            'discount' => $this->discount,
            'fees' => $this->fees,
            'fees_total' => $this->feesTotal,
            'total' => $this->total,
            'lines' => $this->lines,
            'people' => $this->people,
            'promo_code' => $this->promo?->code,
        ];
    }
}
