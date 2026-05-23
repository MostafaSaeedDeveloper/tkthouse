<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class EventLineup extends Model
{
    use HasFactory;

    protected $fillable = [
        'event_id',
        'artist_name',
        'instagram',
        'performance_time',
        'performance_date',
        'image',
        'sort_order',
    ];

    protected function casts(): array
    {
        return [
            'performance_date' => 'date',
        ];
    }

    public function event()
    {
        return $this->belongsTo(Event::class);
    }

    public function getImageUrlAttribute(): ?string
    {
        if (! $this->image) {
            return null;
        }

        if (Str::startsWith($this->image, ['http://', 'https://'])) {
            return $this->image;
        }

        if (Str::startsWith($this->image, 'uploads/')) {
            return asset($this->image);
        }

        return asset('storage/'.$this->image);
    }
}
