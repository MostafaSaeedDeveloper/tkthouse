<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class EventImage extends Model
{
    use HasFactory;

    protected $fillable = [
        'event_id',
        'path',
    ];

    public function event()
    {
        return $this->belongsTo(Event::class);
    }

    public function getPathUrlAttribute(): ?string
    {
        if (! $this->path) {
            return null;
        }

        if (Str::startsWith($this->path, ['http://', 'https://'])) {
            return $this->path;
        }

        if (Str::startsWith($this->path, 'uploads/')) {
            return asset($this->path);
        }

        return asset('storage/'.$this->path);
    }
}
