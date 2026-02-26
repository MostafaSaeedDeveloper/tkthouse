<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class EventFee extends Model
{
    use HasFactory;

    protected $fillable = [
        'event_id',
        'name',
        'fee_type',
        'value',
        'description',
    ];

    public function event()
    {
        return $this->belongsTo(Event::class);
    }
}
