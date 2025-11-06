<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Support\Str;

class NotificationJob extends Model
{
    use HasFactory;

    public $incrementing = false;
    protected $keyType = 'string';

    protected $fillable = [
        'id', 'channel', 'recipient', 'message',
        'status', 'attempts', 'max_attempts',
        'next_run_at', 'idempotency_key',
        'last_error', 'processed_at'
    ];

    protected static function booted()
    {
        static::creating(function ($job) {
            if (empty($job->id)) {
                $job->id = (string) Str::uuid(); 
            }
        });
    }

    protected $casts = [
        'next_run_at' => 'datetime',
        'processed_at' => 'datetime',
    ];
}
