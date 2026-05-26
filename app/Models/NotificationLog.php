<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class NotificationLog extends Model
{
    protected $fillable = [
        'channel',
        'type',
        'recipient',
        'order_id',
        'ticket_id',
        'subject',
        'status',
        'error',
        'metadata',
    ];

    protected $casts = [
        'metadata' => 'array',
    ];

    public static function logEmail(array $attrs): void
    {
        try {
            static::create(['channel' => 'email'] + $attrs);
        } catch (\Throwable) {
        }
    }

    public static function logWhatsapp(array $attrs): void
    {
        try {
            static::create(['channel' => 'whatsapp'] + $attrs);
        } catch (\Throwable) {
        }
    }
}
