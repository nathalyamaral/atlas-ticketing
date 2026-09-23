<?php

namespace App\Console\Commands;

use App\Enums\OutboxStatus;
use App\Jobs\SendTicketNotification;
use App\Models\OutboxEvent;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;

class DispatchOutboxEvents extends Command
{
    protected $signature = 'outbox:dispatch';

    protected $description =
        'Dispatch pending transactional outbox events';

    public function handle(): int
    {
        $lock = Cache::lock(
            'outbox:dispatch',
            30
        );

        if (!$lock->get()) {
            return self::SUCCESS;
        }

        try {
            $events = OutboxEvent::query()
                ->where(
                    'status',
                    OutboxStatus::PENDING->value
                )
                ->where(
                    'available_at',
                    '<=',
                    now()
                )
                ->orderBy('id')
                ->limit(100)
                ->get();

            foreach ($events as $event) {
                try {
                    SendTicketNotification::dispatch(
                        $event->id
                    )->onQueue('notifications');

                    $event->update([
                        'status' =>
                            OutboxStatus::PUBLISHED->value,

                        'published_at' => now(),

                        'attempts' =>
                            $event->attempts + 1,

                        'last_error' => null,
                    ]);
                } catch (\Throwable $exception) {
                    $event->update([
                        'attempts' =>
                            $event->attempts + 1,

                        'last_error' =>
                            mb_substr(
                                $exception->getMessage(),
                                0,
                                2000
                            ),
                    ]);

                    report($exception);
                }
            }
        } finally {
            $lock->release();
        }

        return self::SUCCESS;
    }
}
