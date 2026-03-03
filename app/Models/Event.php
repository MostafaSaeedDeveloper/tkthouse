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
        'slug',
        'event_date',
        'event_time',
        'location',
        'map_url',
        'description',
        'house_rules',
        'cover_image',
        'event_banner',
        'venue_map',
        'status',
        'requires_booking_approval',
    ];

    protected function casts(): array
    {
        return [
            'event_date' => 'date',
            'requires_booking_approval' => 'boolean',
        ];
    }

    protected static function booted(): void
    {
        static::saving(function (self $event) {
            if (empty($event->slug)) {
                $event->slug = static::generateUniqueSlug($event->name, $event->id);
            }
        });
    }

    private static function generateUniqueSlug(string $name, ?int $ignoreId = null): string
    {
        $baseSlug = Str::slug($name);
        $baseSlug = $baseSlug !== '' ? $baseSlug : 'event';
        $slug = $baseSlug;
        $counter = 2;

        while (static::query()
            ->where('slug', $slug)
            ->when($ignoreId, fn ($query) => $query->where('id', '!=', $ignoreId))
            ->exists()) {
            $slug = $baseSlug.'-'.$counter;
            $counter++;
        }

        return $slug;
    }

    public function getRouteKeyName(): string
    {
        return 'slug';
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
        return $this->resolveImageUrl($this->cover_image);
    }

    public function getEventBannerUrlAttribute(): ?string
    {
        return $this->resolveImageUrl($this->event_banner);
    }

    public function getVenueMapUrlAttribute(): ?string
    {
        return $this->resolveImageUrl($this->venue_map);
    }

    private function resolveImageUrl(?string $path): ?string
    {
        if (! $path) {
            return null;
        }

        if (Str::startsWith($path, ['http://', 'https://'])) {
            return $path;
        }

        if (Str::startsWith($path, 'uploads/')) {
            return asset($path);
        }

        return asset('storage/'.$path);
    }
}
