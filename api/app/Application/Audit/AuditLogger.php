<?php

namespace App\Application\Audit;

use App\Models\AuditLog;
use App\Models\User;

class AuditLogger
{
    public function record(
        string $action,
        string $entityType,
        int $entityId,
        ?int $eventId = null,
        User|int|null $actor = null,
        array $metadata = [],
    ): AuditLog {
        $actorId = $actor instanceof User ? $actor->id : $actor;

        return AuditLog::query()->create([
            'event_id' => $eventId,
            'actor_user_id' => $actorId,
            'action' => $action,
            'entity_type' => $entityType,
            'entity_id' => $entityId,
            'metadata' => $metadata ?: null,
            'created_at' => now(),
        ]);
    }
}
