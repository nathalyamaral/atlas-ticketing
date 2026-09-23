<?php

namespace App\Console\Commands;

use App\Enums\SystemAlertStatus;
use App\Models\ConfirmationAttempt;
use App\Models\SystemAlert;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class MonitorConfirmationFailures extends Command
{
    protected $signature = 'monitor:confirmation-failures';

    protected $description = 'Create or resolve an alert based on recent purchase confirmation failures';

    public function handle(): int
    {
        $window = (int) config('monitoring.confirmation_failure.window_minutes');
        $minimum = (int) config('monitoring.confirmation_failure.minimum_attempts');
        $threshold = (float) config('monitoring.confirmation_failure.threshold_percent');
        $since = now()->subMinutes($window);

        $base = ConfirmationAttempt::query()->where('created_at', '>=', $since);
        $total = (clone $base)->count();
        $failures = (clone $base)->where('succeeded', false)->count();
        $rate = $total > 0 ? ($failures / $total) * 100 : 0.0;

        $open = SystemAlert::query()
            ->where('type', 'confirmation_failure_rate')
            ->where('status', SystemAlertStatus::OPEN->value)
            ->first();

        if ($total >= $minimum && $rate >= $threshold) {
            $context = [
                'window_minutes' => $window,
                'attempts' => $total,
                'failures' => $failures,
                'failure_rate_percent' => round($rate, 2),
                'threshold_percent' => $threshold,
            ];

            if ($open) {
                $open->update(['context' => $context]);
            } else {
                SystemAlert::query()->create([
                    'type' => 'confirmation_failure_rate',
                    'status' => SystemAlertStatus::OPEN->value,
                    'message' => 'Purchase confirmation failure rate exceeded the configured threshold.',
                    'context' => $context,
                    'triggered_at' => now(),
                ]);
            }

            Log::warning('Confirmation failure-rate alert is open.', $context);
        } elseif ($open) {
            $open->update([
                'status' => SystemAlertStatus::RESOLVED->value,
                'resolved_at' => now(),
                'context' => [
                    ...(array) $open->context,
                    'resolved_failure_rate_percent' => round($rate, 2),
                    'resolved_attempts' => $total,
                ],
            ]);
        }

        $this->info(sprintf(
            'attempts=%d failures=%d rate=%.2f%% threshold=%.2f%%',
            $total,
            $failures,
            $rate,
            $threshold,
        ));

        return self::SUCCESS;
    }
}
