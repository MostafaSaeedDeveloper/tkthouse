<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class OrderItem extends Model
{
    use HasFactory;

    protected $fillable = [
        'order_id',
        'event_ticket_id',
        'ticket_name',
        'unit_price',
        'quantity',
        'line_total',
    ];

    public function order()
    {
        return $this->belongsTo(Order::class);
    }

    public function eventTicket()
    {
        return $this->belongsTo(EventTicket::class);
    }

    public function tickets()
    {
        return $this->hasMany(Ticket::class);
    }
}
