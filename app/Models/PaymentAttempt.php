<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PaymentAttempt extends Model
{
    public const INITIATED = 'initiated';
    public const PAID = 'paid';
    public const FAILED = 'failed';
    public const EXPIRED = 'expired';

    protected $fillable = [
        'order_id', 'provider', 'method_code', 'reference', 'transaction_id', 'amount', 'currency',
        'status', 'failure_reason', 'meta', 'settled_at',
    ];

    protected function casts(): array
    {
        return [
            'amount' => 'decimal:2',
            'meta' => 'array',
            'settled_at' => 'datetime',
        ];
    }

    public function order()
    {
        return $this->belongsTo(Order::class);
    }

    public function isOpen(): bool
    {
        return $this->status === self::INITIATED;
    }
}
