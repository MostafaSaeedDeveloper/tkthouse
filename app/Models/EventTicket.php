<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class EventTicket extends Model
{
    use HasFactory;

    protected $fillable = [
        'event_id',
        'name',
        'price',
        'discount_type',
        'discount_value',
        'discount_starts_at',
        'discount_ends_at',
        'status',
        'color',
        'label',
        'description',
        'max_per_order',
        'is_couple',
        'gender',
        'sort_order',
    ];

    protected $casts = [
        'is_couple' => 'boolean',
        'discount_value' => 'float',
        'discount_starts_at' => 'datetime',
        'discount_ends_at' => 'datetime',
    ];

    /** True while a configured discount is running (started, not ended, and actually lowers the price). */
    public function hasActiveDiscount(): bool
    {
        if (! in_array($this->discount_type, ['percent', 'fixed'], true) || (float) $this->discount_value <= 0 || ! $this->discount_ends_at) {
            return false;
        }
        if ($this->discount_starts_at && now()->lt($this->discount_starts_at)) {
            return false;
        }
        if (now()->gte($this->discount_ends_at)) {
            return false;
        }

        return $this->currentPrice() < round((float) $this->price, 2);
    }

    /** The price a customer pays right now: the discounted price while the offer runs, otherwise the regular price. */
    public function currentPrice(): float
    {
        $price = round((float) $this->price, 2);
        if (! in_array($this->discount_type, ['percent', 'fixed'], true) || (float) $this->discount_value <= 0 || ! $this->discount_ends_at) {
            return $price;
        }
        if (($this->discount_starts_at && now()->lt($this->discount_starts_at)) || now()->gte($this->discount_ends_at)) {
            return $price;
        }
        $off = $this->discount_type === 'percent' ? round($price * min(100, (float) $this->discount_value) / 100, 2) : (float) $this->discount_value;

        return round(max(0, $price - $off), 2);
    }

    public function discountPercentLabel(): ?string
    {
        if (! $this->hasActiveDiscount() || (float) $this->price <= 0) {
            return null;
        }

        return '-'.rtrim(rtrim(number_format((1 - $this->currentPrice() / (float) $this->price) * 100, 1), '0'), '.').'%';
    }

    public function event()
    {
        return $this->belongsTo(Event::class);
    }
}
