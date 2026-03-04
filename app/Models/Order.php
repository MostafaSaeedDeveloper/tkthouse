<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Order extends Model
{
    use HasFactory;

    protected $fillable = [
        'customer_id',
        'user_id',
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
        'approved_at',
        'tickets_generated_at',
        'total_amount',
    ];

    protected function casts(): array
    {
        return [
            'requires_approval' => 'boolean',
            'approved_at' => 'datetime',
            'tickets_generated_at' => 'datetime',
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

    public function issuedTickets()
    {
        return $this->hasMany(IssuedTicket::class);
    }
}
