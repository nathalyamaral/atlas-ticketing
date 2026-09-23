<?php

namespace App\Console\Commands;

use App\Enums\NotificationStatus;
use App\Jobs\SendTicketNotification;
use App\Models\NotificationDelivery;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Throwable;

class RetryFailedNotifications extends Command
{
    protected $signature = 'notifications:retry-failed
                            {--id= : Retry a specific notification delivery}';

    protected $description = 'Requeue failed ticket notification deliveries';

    public function handle(): int
    {
        $query = NotificationDelivery::query()
            ->where('status', NotificationStatus::FAILED->value);

        if ($id = $this->option('id')) {
            $query->whereKey($id);
        }

        $deliveries = $query->orderBy('id')->get();

        if ($deliveries->isEmpty()) {
            $this->info('No failed notifications to retry.');
            return self::SUCCESS;
        }

        foreach ($deliveries as $delivery) {
            $outboxEventId = DB::transaction(function () use ($delivery) {
                $locked = NotificationDelivery::query()
                    ->whereKey($delivery->id)
                    ->lockForUpdate()
                    ->firstOrFail();

                if ($locked->status !== NotificationStatus::FAILED) {
                    return null;
                }

                $locked->update([
                    'status' => NotificationStatus::PENDING->value,
                    'last_error' => null,
                ]);

                return $locked->outbox_event_id;
            });

            if ($outboxEventId === null) {
                continue;
            }

            try {
                SendTicketNotification::dispatch($outboxEventId)
                    ->onQueue('notifications');
                $this->info("Notification {$delivery->id} requeued.");
            } catch (Throwable $exception) {
                NotificationDelivery::query()->whereKey($delivery->id)->update([
                    'status' => NotificationStatus::FAILED->value,
                    'last_error' => mb_substr($exception->getMessage(), 0, 2000),
                ]);
                $this->error("Notification {$delivery->id} could not be requeued: {$exception->getMessage()}");
            }
        }

        return self::SUCCESS;
    }
}
