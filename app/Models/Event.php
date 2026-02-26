<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

class Event extends Model
{
    use HasFactory, LogsActivity;

    protected $fillable = [
        'name',
        'event_date',
        'event_time',
        'location',
        'map_url',
        'description',
        'house_rules',
        'cover_image',
        'status',
    ];

    protected function casts(): array
    {
        return [
            'event_date' => 'date',
        ];
    }

    public function tickets()
    {
        return $this->hasMany(EventTicket::class);
    }

    public function fees()
    {
        return $this->hasMany(EventFee::class);
    }

    public function images()
    {
        return $this->hasMany(EventImage::class);
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logFillable()
            ->logOnlyDirty();
    }

    public function getCoverImageUrlAttribute(): ?string
    {
        if (! $this->cover_image) {
            return null;
        }

        if (Str::startsWith($this->cover_image, ['http://', 'https://'])) {
            return $this->cover_image;
        }

        if (Str::startsWith($this->cover_image, 'uploads/')) {
            return asset($this->cover_image);
        }

        return asset('storage/'.$this->cover_image);
    }
}
