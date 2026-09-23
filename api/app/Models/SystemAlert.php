<?php

namespace App\Models;

use App\Enums\SystemAlertStatus;
use Illuminate\Database\Eloquent\Model;

class SystemAlert extends Model
{
    protected $fillable = [
        'type',
        'status',
        'message',
        'context',
        'triggered_at',
        'resolved_at',
    ];

    protected function casts(): array
    {
        return [
            'status' => SystemAlertStatus::class,
            'context' => 'array',
            'triggered_at' => 'datetime',
            'resolved_at' => 'datetime',
        ];
    }
}
