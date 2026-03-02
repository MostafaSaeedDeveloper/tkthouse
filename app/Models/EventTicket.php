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
        'status',
        'color',
        'label',
        'description',
        'max_per_order',
        'is_couple',
    ];

    public function event()
    {
        return $this->belongsTo(Event::class);
    }
}
