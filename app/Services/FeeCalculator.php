<?php

namespace App\Services;

use App\Models\FeesPolicy;
use App\Models\Order;
use App\Models\TicketType;

class FeeCalculator
{
    public function calculate(Order $order): array
    {
        $order->loadMissing('items.ticketType.event.feesPolicy.rules', 'paymentMethod.feesPolicy.rules');

        $feesTotal = 0.0;
        $itemFees = [];

        foreach ($order->items as $item) {
            $policy = $this->resolvePolicy($item->ticketType, $order);
            $lineFees = $this->calculateFromPolicy($policy, (float) $item->line_subtotal, (int) $item->qty);
            $itemFees[$item->id] = round($lineFees, 2);
            $feesTotal += $lineFees;
        }

        return [
            'fees_total' => round($feesTotal, 2),
            'item_fees' => $itemFees,
        ];
    }

    protected function resolvePolicy(TicketType $ticketType, Order $order): ?FeesPolicy
    {
        return $ticketType->feesPolicy
            ?? $ticketType->event?->feesPolicy
            ?? $order->paymentMethod?->feesPolicy
            ?? FeesPolicy::query()->where('scope', 'global')->where('is_active', true)->first();
    }

    protected function calculateFromPolicy(?FeesPolicy $policy, float $lineSubtotal, int $qty): float
    {
        if (! $policy) {
            return 0.0;
        }

        $total = 0.0;

        foreach ($policy->rules as $rule) {
            $ruleValue = $rule->type === 'percent'
                ? ($lineSubtotal * ((float) $rule->value / 100))
                : (float) $rule->value;

            if ($rule->applies_to === 'ticket') {
                $ruleValue *= max($qty, 1);
            }

            $min = $rule->min_amount !== null ? (float) $rule->min_amount : null;
            $max = $rule->max_amount !== null ? (float) $rule->max_amount : null;

            if ($min !== null) {
                $ruleValue = max($min, $ruleValue);
            }

            if ($max !== null) {
                $ruleValue = min($max, $ruleValue);
            }

            $total += $ruleValue;
        }

        return $total;
    }
}
