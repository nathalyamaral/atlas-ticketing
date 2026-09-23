<?php

namespace App\Jobs;

use App\Contracts\NotificationProvider;
use App\Enums\NotificationStatus;
use App\Exceptions\NotificationProviderException;
use App\Models\NotificationDelivery;
use App\Models\Order;
use App\Models\OutboxEvent;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Throwable;

class SendTicketNotification implements ShouldQueue, ShouldBeUnique
{
    use Queueable;

    public int $tries = 5;
    public int $timeout = 10;
    public int $uniqueFor = 120;

    public function __construct(public int $outboxEventId)
    {
    }

    public function uniqueId(): string
    {
        return (string) $this->outboxEventId;
    }

    public function backoff(): array
    {
        return [5, 15, 30, 60];
    }

    public function handle(NotificationProvider $provider): void
    {
        $outbox = OutboxEvent::query()->findOrFail($this->outboxEventId);
        $delivery = NotificationDelivery::query()
            ->where('outbox_event_id', $outbox->id)
            ->firstOrFail();

        if ($delivery->status === NotificationStatus::SENT) {
            return;
        }

        $order = Order::query()
            ->with(['buyer:id,name,email', 'event:id,name,location,starts_at', 'tickets.seat'])
            ->findOrFail($outbox->payload['order_id']);

        $delivery->increment('attempts');
        $delivery->update(['last_attempt_at' => now()]);

        try {
            $statusCode = $provider->send(
                $delivery->notification_id,
                [
                    'notification_id' => $delivery->notification_id,
                    'type' => 'purchase.confirmed',
                    'buyer' => [
                        'name' => $order->buyer->name,
                        'email' => $order->buyer->email,
                    ],
                    'order' => [
                        'id' => $order->id,
                        'event' => [
                            'name' => $order->event->name,
                            'location' => $order->event->location,
                            'starts_at' => $order->event->starts_at->toISOString(),
                        ],
                        'tickets' => $order->tickets->map(fn ($ticket) => [
                            'code' => $ticket->code,
                            'seat' => [
                                'sector' => $ticket->seat->sector,
                                'row' => $ticket->seat->row_label,
                                'number' => $ticket->seat->number,
                            ],
                        ])->values()->all(),
                    ],
                ]
            );

            $delivery->update([
                'status' => NotificationStatus::SENT->value,
                'provider_status_code' => $statusCode,
                'sent_at' => now(),
                'last_error' => null,
            ]);
        } catch (Throwable $exception) {
            $attributes = [
                'last_error' => mb_substr($exception->getMessage(), 0, 2000),
            ];

            if ($exception instanceof NotificationProviderException) {
                $attributes['provider_status_code'] = $exception->statusCode;
            }

            $delivery->update($attributes);
            throw $exception;
        }
    }

    public function failed(?Throwable $exception): void
    {
        NotificationDelivery::query()
            ->where('outbox_event_id', $this->outboxEventId)
            ->update([
                'status' => NotificationStatus::FAILED->value,
                'last_error' => mb_substr(
                    $exception?->getMessage() ?? 'Unknown error',
                    0,
                    2000,
                ),
            ]);
    }
}
