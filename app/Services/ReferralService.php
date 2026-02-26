<?php

namespace App\Services;

use App\Models\Order;
use App\Models\Referral;

class ReferralService
{
    public function qualifyOnFirstPaidOrder(Order $order): void
    {
        if (! $order->user_id) return;

        $ref = Referral::where('referred_user_id', $order->user_id)->where('status', 'pending')->first();
        if (! $ref) return;

        $ref->update(['status' => 'qualified', 'qualified_at' => now(), 'reward_amount' => 25]);
        app(WalletService::class)->credit($ref->referrer_user_id, 25, 'referral', Referral::class, $ref->id);
        $ref->update(['status' => 'paid', 'paid_at' => now()]);
    }
}
