<?php

namespace App\Models;

use App\Models\Event;
use App\Notifications\CustomerResetPasswordNotification;
// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;
use Spatie\Permission\Traits\HasRoles;

class User extends Authenticatable
{
    /** @use HasFactory<\Database\Factories\UserFactory> */
    use HasFactory, Notifiable, HasRoles, LogsActivity;

    protected $fillable = [
        'name',
        'username',
        'email',
        'phone',
        'affiliate_code',
        'affiliate_target_url',
        'referred_by_user_id',
        'profile_image',
        'password',
        'last_login_at',
        'last_login_ip',
    ];

    protected $hidden = [
        'password',
        'remember_token',
    ];

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'last_login_at' => 'datetime',
        ];
    }



    public function profileImageUrl(): ?string
    {
        if (! $this->profile_image) {
            return null;
        }

        if (Str::startsWith($this->profile_image, ['http://', 'https://'])) {
            return $this->profile_image;
        }

        return Storage::disk('public')->url($this->profile_image);
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logOnly(['name', 'username', 'email', 'phone', 'profile_image', 'last_login_at', 'last_login_ip'])
            ->logOnlyDirty()
            ->dontSubmitEmptyLogs();
    }


    public function managedEvents(): \Illuminate\Database\Eloquent\Relations\BelongsToMany
    {
        return $this->belongsToMany(Event::class, 'user_managed_events');
    }

    public function getManagedEventAttribute(): ?Event
    {
        return $this->managedEvents->first();
    }

    public function getManagedEventIdsAttribute(): array
    {
        return $this->managedEvents->pluck('id')->all();
    }

    public function referredBy()
    {
        return $this->belongsTo(self::class, 'referred_by_user_id');
    }

    public function referredUsers()
    {
        return $this->hasMany(self::class, 'referred_by_user_id');
    }

    public function affiliateOrders()
    {
        return $this->hasMany(Order::class, 'affiliate_user_id');
    }

    public function scanLogs()
    {
        return $this->hasMany(ScanLog::class, 'scanned_by_user_id');
    }

    public function sendPasswordResetNotification($token): void
    {
        $this->notify(new CustomerResetPasswordNotification($token));
    }
}
