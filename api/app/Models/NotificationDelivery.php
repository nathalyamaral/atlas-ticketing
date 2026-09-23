<?php

namespace App\Models;

use App\Enums\NotificationStatus;
use Illuminate\Database\Eloquent\Model;

class NotificationDelivery extends Model
{
    protected $fillable = [
        'outbox_event_id',
        'notification_id',
        'status',
        'attempts',
        'provider_status_code',
        'last_attempt_at',
        'sent_at',
        'last_error',
    ];

    protected function casts(): array
    {
        return [
            'status' => NotificationStatus::class,
            'last_attempt_at' => 'datetime',
            'sent_at' => 'datetime',
        ];
    }
}
