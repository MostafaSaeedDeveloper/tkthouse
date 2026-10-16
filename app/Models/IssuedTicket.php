<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class IssuedTicket extends Model
{
    use HasFactory;

    protected $fillable = [
        'order_id',
        'order_item_id',
        'uuid',
        'ticket_number',
        'holder_name',
        'holder_email',
        'holder_phone',
        'ticket_name',
        'ticket_price',
        'seat_index',
        'sent_at',
    ];

    protected function casts(): array
    {
        return [
            'ticket_price' => 'decimal:2',
            'sent_at' => 'datetime',
        ];
    }

    public function order()
    {
        return $this->belongsTo(Order::class);
    }

    public function orderItem()
    {
        return $this->belongsTo(OrderItem::class);
    }

    public function dashboardTicket()
    {
        return $this->hasOne(Ticket::class, 'ticket_number', 'ticket_number');
    }

    /**
     * Tickets a signed-in customer may open: those of their own (portal-visible) paid orders, plus tickets issued to
     * their *verified* e-mail as a holder (a gifted ticket) when the order itself is paid and claimed.
     */
    public function scopeOwnedByCustomer($query, \App\Models\User $user)
    {
        // One grouped condition, so extra constraints added by the caller (e.g. whereKey) can never be OR-ed around it.
        return $query->where(function ($outer) use ($user) {
            $outer->whereHas('order', fn ($order) => $order->where('status', 'paid')->ownedByCustomer($user));

            if ($user->email_verified_at) {
                $outer->orWhere(fn ($gift) => $gift->whereRaw('LOWER(holder_email) = ?', [mb_strtolower((string) $user->email)])
                    ->whereHas('order', fn ($o) => $o->where('status', 'paid')->where('owner_unverified', false)));
            }
        });
    }

    public function getRouteKeyName(): string
    {
        return 'uuid';
    }

    public function qrUrl(): string
    {
        return 'https://api.qrserver.com/v1/create-qr-code/?size=260x260&data='.urlencode((string) $this->ticket_number);
    }
}
