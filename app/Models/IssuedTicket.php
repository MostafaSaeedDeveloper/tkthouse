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

    public function getRouteKeyName(): string
    {
        return 'uuid';
    }

    public function qrUrl(): string
    {
        return 'https://api.qrserver.com/v1/create-qr-code/?size=260x260&data='.urlencode(route('front.tickets.show', $this));
    }
}
