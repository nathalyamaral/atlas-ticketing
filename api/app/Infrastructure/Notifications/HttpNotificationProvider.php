<?php

namespace App\Infrastructure\Notifications;

use App\Contracts\NotificationProvider;
use App\Exceptions\NotificationProviderException;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;

class HttpNotificationProvider implements NotificationProvider
{
    public function send(string $idempotencyKey, array $payload): int
    {
        try {
            $response = Http::connectTimeout(
                config('services.notification_provider.connect_timeout')
            )
                ->timeout(config('services.notification_provider.timeout'))
                ->acceptJson()
                ->withHeaders(['Idempotency-Key' => $idempotencyKey])
                ->post(
                    rtrim(config('services.notification_provider.url'), '/') . '/notifications',
                    $payload
                );
        } catch (ConnectionException $exception) {
            throw new NotificationProviderException(
                'Notification provider connection failed: ' . $exception->getMessage(),
                null,
            );
        }

        if (! $response->successful()) {
            throw new NotificationProviderException(
                sprintf('Notification provider returned HTTP %d.', $response->status()),
                $response->status(),
            );
        }

        return $response->status();
    }
}
