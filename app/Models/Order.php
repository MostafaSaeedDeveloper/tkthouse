<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Order extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'customer_id',
        'user_id',
        'affiliate_user_id',
        'promo_code_id',
        'promo_code',
        'discount_amount',
        'subtotal_amount',
        'order_number',
        'status',
        'requires_approval',
        'payment_method',
        'payment_link_token',
        'payment_timeout_started_at',
        'paid_at',
        'approved_at',
        'tickets_generated_at',
        'total_amount',
        'exclude_from_statistics',
    ];

    protected function casts(): array
    {
        return [
            'requires_approval' => 'boolean',
            'exclude_from_statistics' => 'boolean',
            'approved_at' => 'datetime',
            'paid_at' => 'datetime',
            'tickets_generated_at' => 'datetime',
            'payment_timeout_started_at' => 'datetime',
            'discount_amount' => 'decimal:2',
            'subtotal_amount' => 'decimal:2',
        ];
    }

    public function customer()
    {
        return $this->belongsTo(Customer::class);
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function items()
    {
        return $this->hasMany(OrderItem::class);
    }

    public function affiliateUser()
    {
        return $this->belongsTo(User::class, 'affiliate_user_id');
    }

    public function promoCode()
    {
        return $this->belongsTo(PromoCode::class);
    }

    public function issuedTickets()
    {
        return $this->hasMany(IssuedTicket::class);
    }

    public function scopeIncludedInStatistics($query)
    {
        return $query->where('exclude_from_statistics', false);
    }

    public function paymentDeadlineAt(?int $timeoutMinutes = null): ?\Illuminate\Support\Carbon
    {
        if ($this->status !== 'pending_payment' || ! $this->payment_timeout_started_at) {
            return null;
        }

        $timeout = max(1, (int) ($timeoutMinutes ?? \App\Support\SystemSettings::pendingPaymentTimeoutMinutes()));

        return $this->payment_timeout_started_at?->copy()->addMinutes($timeout);
    }

    public function paymentSecondsRemaining(?int $timeoutMinutes = null): ?int
    {
        $deadline = $this->paymentDeadlineAt($timeoutMinutes);
        if (! $deadline) {
            return null;
        }

        return now()->diffInSeconds($deadline, false);
    }
}
