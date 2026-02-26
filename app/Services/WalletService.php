<?php

namespace App\Services;

use App\Models\User;
use App\Models\WalletTransaction;

class WalletService
{
    public function credit(int $userId, float $amount, string $reason, ?string $referenceType = null, ?int $referenceId = null): WalletTransaction
    {
        $user = User::findOrFail($userId);
        $user->increment('wallet_balance', $amount);

        return WalletTransaction::create([
            'user_id' => $userId,
            'type' => 'credit',
            'amount' => $amount,
            'reason' => $reason,
            'reference_type' => $referenceType,
            'reference_id' => $referenceId,
        ]);
    }
}
