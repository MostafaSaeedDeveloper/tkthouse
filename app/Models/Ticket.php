<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Ticket extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'order_id',
        'order_item_id',
        'event_id',
        'name',
        'source',
        'guest_type',
        'price',
        'description',
        'status',
        'ticket_number',
        'holder_name',
        'holder_email',
        'holder_phone',
        'holder_gender',
        'qr_payload',
        'issued_at',
        'checked_in_at',
        'canceled_at',
        'created_by_user_id',
        'cancellation_source',
        'cancellation_reason',
        'canceled_by_refund_id',
        'refund_flag',
    ];

    protected function casts(): array
    {
        return [
            'issued_at' => 'datetime',
            'checked_in_at' => 'datetime',
            'canceled_at' => 'datetime',
        ];
    }

    /** Guest tickets have no assignment: everyone who can open the guest list (permission-gated by the routes) sees all of them. */
    public function scopeGuestListVisibleTo($query, ?User $user)
    {
        return $query;
    }

    public function isGuestVisibleTo(?User $user): bool
    {
        return true;
    }

    public function event()
    {
        return $this->belongsTo(Event::class);
    }

    public function orderItems()
    {
        return $this->hasMany(OrderItem::class);
    }

    public function order()
    {
        return $this->belongsTo(Order::class);
    }

    public function orderItem()
    {
        return $this->belongsTo(OrderItem::class);
    }

    public const SOURCE_REFUND = 'refund';

    /** Invalidated by a confirmed refund (as opposed to an administrative cancellation). Such a ticket can never be re-opened by status edits. */
    public function isRefundCanceled(): bool
    {
        return $this->status === 'canceled' && ($this->cancellation_source === self::SOURCE_REFUND || $this->canceled_by_refund_id !== null);
    }

    /** Text safe to show to scanner staff: the refund reason only, never amounts, provider data or internal notes. */
    public function refundCancellationText(): string
    {
        $reason = trim((string) $this->cancellation_reason);

        return 'Refund confirmed'.($reason !== '' ? ' — '.$reason : '');
    }

    public function refundCancellations()
    {
        return $this->hasMany(PaymentRefundTicket::class);
    }

    public function eventLabel(): string
    {
        $name = (string) ($this->name ?? '');

        return trim((string) strstr($name, ' - ', true)) ?: $name;
    }

    public function isGuestList(): bool
    {
        return $this->source === 'guest_list';
    }

    public function guestTypeLabel(): ?string
    {
        if (! $this->isGuestList()) {
            return null;
        }

        return $this->guest_type ? 'Guest '.trim((string) $this->guest_type) : 'Guest';
    }

    public function ticketTypeLabel(): string
    {
        $name = (string) ($this->name ?? '');

        if (! str_contains($name, ' - ')) {
            return $name;
        }

        return trim((string) substr(strstr($name, ' - '), 3));
    }
}
