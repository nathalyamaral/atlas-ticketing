<?php

namespace App\Contracts;

interface NotificationProvider
{
    public function send(
        string $idempotencyKey,
        array $payload,
    ): int;
}
