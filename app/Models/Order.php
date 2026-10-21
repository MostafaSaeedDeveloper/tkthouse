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
        'assigned_user_id',
        'assigned_by_user_id',
        'assigned_at',
        'sold_by_user_id',
        'sold_at',
        'integration_account_id',
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
            'assigned_at' => 'datetime',
            'sold_at' => 'datetime',
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

    public function integrationAccount()
    {
        return $this->belongsTo(IntegrationAccount::class);
    }

    public function assignedUser()
    {
        return $this->belongsTo(User::class, 'assigned_user_id');
    }

    /** Every agent on this order (the primary included). `assigned_user_id` is the primary one. */
    public function assignees()
    {
        return $this->belongsToMany(User::class, 'order_assignees')->withPivot('assigned_by_user_id')->withTimestamps()->orderBy('order_assignees.id');
    }

    /** @return list<int> primary first, then the others */
    public function assigneeIds(): array
    {
        $ids = $this->relationLoaded('assignees') ? $this->assignees->pluck('id')->all() : $this->assignees()->pluck('users.id')->all();
        $primary = $this->assigned_user_id ? [(int) $this->assigned_user_id] : [];

        return array_values(array_unique(array_merge($primary, array_map('intval', $ids))));
    }

    public function isAssignedTo(?User $user): bool
    {
        return $user !== null && in_array((int) $user->id, $this->assigneeIds(), true);
    }

    public function assignedBy()
    {
        return $this->belongsTo(User::class, 'assigned_by_user_id');
    }

    public function soldBy()
    {
        return $this->belongsTo(User::class, 'sold_by_user_id');
    }

    public function assignments()
    {
        return $this->hasMany(OrderAssignment::class)->orderByDesc('id');
    }

    public function paymentAttempts()
    {
        return $this->hasMany(PaymentAttempt::class);
    }

    public function latestPaymentAttempt()
    {
        return $this->hasOne(PaymentAttempt::class)->latestOfMany();
    }

    public function issuedTickets()
    {
        return $this->hasMany(IssuedTicket::class);
    }

    /**
     * Orders a signed-in customer may see in their portal. Ownership is never "same e-mail typed at checkout":
     *  - orders bound to the account (user_id), unless they were placed before the mailbox was proven (owner_unverified), or
     *  - legacy/back-office orders with no account at all whose contact e-mail equals the account's *verified* e-mail.
     */
    public function scopeOwnedByCustomer($query, \App\Models\User $user)
    {
        return $query->where('owner_unverified', false)->where(function ($q) use ($user) {
            $q->where('user_id', $user->id);

            if ($user->email_verified_at) {
                $q->orWhere(fn ($w) => $w->whereNull('user_id')
                    ->whereHas('customer', fn ($c) => $c->whereRaw('LOWER(email) = ?', [mb_strtolower((string) $user->email)])));
            }
        });
    }

    public function scopeIncludedInStatistics($query)
    {
        return $query->where('exclude_from_statistics', false);
    }

    /** Permission that unlocks orders flagged "exclude from statistics" in the admin panel. */
    public const SHOW_HIDDEN_PERMISSION = 'showing_orders';

    /** Back-office visibility: hidden (statistics-excluded) orders need the `showing_orders` permission. */
    public function scopeVisibleTo($query, ?\App\Models\User $user, bool $openEvents = false)
    {
        $query = $user?->can(self::SHOW_HIDDEN_PERMISSION) ? $query : $query->includedInStatistics();

        return \App\Orders\OrderAccess::apply($query, $user, $this->getTable(), $openEvents);
    }

    /** Assignment scoping only (no hidden-order rule) for statistics queries that already use includedInStatistics(). */
    public function scopeAssignedScope($query, ?\App\Models\User $user)
    {
        return \App\Orders\OrderAccess::apply($query, $user, $this->getTable());
    }

    public function isVisibleTo(?\App\Models\User $user): bool
    {
        return (! $this->exclude_from_statistics || (bool) $user?->can(self::SHOW_HIDDEN_PERMISSION))
            && \App\Orders\OrderAccess::canView($this, $user);
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
