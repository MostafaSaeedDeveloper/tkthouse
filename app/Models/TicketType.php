<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TicketType extends Model
{
    use HasFactory;

    protected $guarded = [];

    protected function casts(): array
    {
        return ['sale_starts_at' => 'datetime', 'sale_ends_at' => 'datetime', 'is_available' => 'boolean'];
    }

    public function event(): BelongsTo { return $this->belongsTo(Event::class); }
    public function feesPolicy(): BelongsTo { return $this->belongsTo(FeesPolicy::class); }
}
