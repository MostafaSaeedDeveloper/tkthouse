<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Ticket extends Model
{
    use HasFactory;

    protected $fillable = [
        'order_id',
        'order_item_id',
        'name',
        'price',
        'description',
        'status',
        'ticket_number',
        'holder_name',
        'holder_email',
        'holder_phone',
        'qr_payload',
        'issued_at',
        'checked_in_at',
        'canceled_at',
    ];

    protected function casts(): array
    {
        return [
            'issued_at' => 'datetime',
            'checked_in_at' => 'datetime',
            'canceled_at' => 'datetime',
        ];
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

    public function eventLabel(): string
    {
        $name = (string) ($this->name ?? '');

        return trim((string) strstr($name, ' - ', true)) ?: $name;
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
