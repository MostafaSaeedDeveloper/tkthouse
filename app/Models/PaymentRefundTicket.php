<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/** A ticket attached to a refund: which tickets the refund covers and, once confirmed, which it really invalidated. */
class PaymentRefundTicket extends Model
{
    protected $fillable = ['payment_refund_id', 'ticket_id', 'amount_minor', 'canceled_at', 'was_checked_in'];

    protected function casts(): array
    {
        return ['amount_minor' => 'integer', 'canceled_at' => 'datetime', 'was_checked_in' => 'boolean'];
    }

    public function refund()
    {
        return $this->belongsTo(PaymentRefund::class, 'payment_refund_id');
    }

    public function ticket()
    {
        return $this->belongsTo(Ticket::class);
    }
}
