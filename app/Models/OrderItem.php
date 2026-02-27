<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class OrderItem extends Model
{
    use HasFactory;

    protected $fillable = [
        'order_id',
        'ticket_id',
        'ticket_name',
        'ticket_price',
        'quantity',
        'line_total',
        'holder_name',
        'holder_email',
        'holder_phone',
        'holder_gender',
        'holder_social_profile',
    ];

    public function order()
    {
        return $this->belongsTo(Order::class);
    }

    public function ticket()
    {
        return $this->belongsTo(Ticket::class);
    }

    public function issuedTickets()
    {
        return $this->hasMany(IssuedTicket::class);
    }
}
