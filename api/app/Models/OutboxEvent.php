<?php

namespace App\Models;

use App\Enums\OutboxStatus;
use Illuminate\Database\Eloquent\Model;

class OutboxEvent extends Model
{
    protected $fillable = [
        'event_id',
        'type',
        'aggregate_type',
        'aggregate_id',
        'payload',
        'status',
        'attempts',
        'available_at',
        'published_at',
        'last_error',
    ];

    protected function casts(): array
    {
        return [
            'payload' => 'array',
            'status' => OutboxStatus::class,
            'available_at' => 'datetime',
            'published_at' => 'datetime',
        ];
    }
}
