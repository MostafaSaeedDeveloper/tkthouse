<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ScanLog extends Model
{
    use HasFactory;

    protected $fillable = [
        'ticket_id',
        'event_name',
        'ticket_number',
        'scanned_by_user_id',
        'scanner_name',
        'action',
        'previous_status',
        'new_status',
        'payload',
        'ip_address',
        'user_agent',
        'scanned_at',
    ];

    protected $casts = [
        'scanned_at' => 'datetime',
    ];

    public function ticket()
    {
        return $this->belongsTo(Ticket::class);
    }

    public function scannerUser()
    {
        return $this->belongsTo(User::class, 'scanned_by_user_id');
    }
}
