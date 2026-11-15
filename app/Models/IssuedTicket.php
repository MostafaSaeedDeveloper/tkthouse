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
        'holder_email_changes',
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
            // Refunded / canceled orders stay listed so the customer sees the true status; their tickets never get a QR (see CustomerPortal::qrState).
            $outer->whereHas('order', fn ($order) => $order->whereIn('status', self::PORTAL_ORDER_STATUSES)->ownedByCustomer($user));

            // Guest-list tickets have no order: their holder (a verified e-mail) is the only way in.
            if ($user->email_verified_at) {
                $outer->orWhere(fn ($guest) => $guest->whereNull('order_id')->whereRaw('LOWER(holder_email) = ?', [mb_strtolower((string) $user->email)]));
            }

            // A gifted ticket: its holder proved the mailbox with a code, which is all that is needed to open *that* ticket,
            // even when the buyer paid as a guest and never signed in.
            if ($user->email_verified_at) {
                $outer->orWhere(fn ($gift) => $gift->whereRaw('LOWER(holder_email) = ?', [mb_strtolower((string) $user->email)])
                    ->whereHas('order', fn ($o) => $o->whereIn('status', self::PORTAL_ORDER_STATUSES)));
            }
        });
    }

    /** Order statuses whose tickets a customer can open (valid or not). Tickets exist only for orders that were paid at some point. */
    public const PORTAL_ORDER_STATUSES = ['paid', 'partially_refunded', 'refunded', 'canceled', 'cancelled'];

    public function getRouteKeyName(): string
    {
        return 'uuid';
    }

    /** The event of this ticket: through its order item, or (guest-list tickets) through the dashboard ticket. */
    public function event(): ?Event
    {
        return $this->orderItem?->event ?? $this->dashboardTicket?->event;
    }

    public function isGuestList(): bool
    {
        return $this->order_id === null;
    }

    /** "Event - VIP" → "VIP" (the whole name when it has no event prefix). */
    public function ticketTypeLabel(): string
    {
        $name = (string) $this->ticket_name;

        return str_contains($name, ' - ') ? trim((string) substr(strstr($name, ' - '), 3)) : $name;
    }

    /** What the QR code of this ticket encodes (the dashboard ticket's payload; the bare number for tickets without one). */
    public function qrPayload(): string
    {
        return (string) ($this->dashboardTicket?->qr_payload ?: $this->ticket_number);
    }

    /** Self-contained image (no third-party QR service). Only call this once \App\Support\CustomerPortal::qrState() says "visible". */
    public function qrUrl(): string
    {
        return \App\Support\TicketQr::dataUri($this->qrPayload());
    }
}
