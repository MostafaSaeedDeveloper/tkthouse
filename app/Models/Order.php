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
        'owner_unverified',
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
        'payment_timeout_minutes',
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
            'owner_unverified' => 'boolean',
            'exclude_from_statistics' => 'boolean',
            'approved_at' => 'datetime',
            'paid_at' => 'datetime',
            'tickets_generated_at' => 'datetime',
            'payment_timeout_started_at' => 'datetime',
            'payment_timeout_minutes' => 'integer',
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

    public function paymentAttempts()
    {
        return $this->hasMany(PaymentAttempt::class);
    }

    public function issuedTickets()
    {
        return $this->hasMany(IssuedTicket::class);
    }

    public function scopeIncludedInStatistics($query)
    {
        return $query->where('exclude_from_statistics', false);
    }

    /** Permission that unlocks orders flagged "exclude from statistics" in the admin panel. */
    public const SHOW_HIDDEN_PERMISSION = 'showing_orders';

    /** Back-office visibility: hidden (statistics-excluded) orders need the `showing_orders` permission. */
    public function scopeVisibleTo($query, ?\App\Models\User $user)
    {
        return $user?->can(self::SHOW_HIDDEN_PERMISSION) ? $query : $query->includedInStatistics();
    }

    public function isVisibleTo(?\App\Models\User $user): bool
    {
        return ! $this->exclude_from_statistics || (bool) $user?->can(self::SHOW_HIDDEN_PERMISSION);
    }

    public function paymentDeadlineAt(?int $timeoutMinutes = null): ?\Illuminate\Support\Carbon
    {
        if ($this->status !== 'pending_payment') {
            return null;
        }

        $timeout = max(1, (int) ($timeoutMinutes ?? $this->payment_timeout_minutes ?? \App\Support\SystemSettings::pendingPaymentTimeoutMinutes()));
        $startAt = $this->payment_timeout_started_at;

        if (! $startAt) {
            $startAt = $this->created_at;
        }

        return $startAt?->copy()->addMinutes($timeout);
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
