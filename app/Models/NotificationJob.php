<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class NotificationJob extends Model
{
    protected $fillable = [
        'channel', 'recipient', 'message',
        'status', 'attempts', 'max_attempts',
        'next_run_at', 'idempotency_key',
        'last_error', 'processed_at'
    ];

    protected $casts = [
        'next_run_at' => 'datetime',
        'processed_at' => 'datetime',
    ];
}
