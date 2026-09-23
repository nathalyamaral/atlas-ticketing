<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ConfirmationAttempt extends Model
{
    public $timestamps = false;

    protected $fillable = [
        'reservation_id',
        'buyer_id',
        'succeeded',
        'failure_reason',
        'duration_ms',
        'created_at',
    ];

    protected function casts(): array
    {
        return [
            'succeeded' => 'boolean',
            'created_at' => 'datetime',
        ];
    }
}
