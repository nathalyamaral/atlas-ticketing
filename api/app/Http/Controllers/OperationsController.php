<?php

namespace App\Http\Controllers;

use App\Enums\NotificationStatus;
use App\Enums\OutboxStatus;
use App\Enums\SystemAlertStatus;
use App\Enums\UserRole;
use App\Models\ConfirmationAttempt;
use App\Models\NotificationDelivery;
use App\Models\OutboxEvent;
use App\Models\SystemAlert;
use Illuminate\Http\Request;

class OperationsController extends Controller
{
    public function metrics(Request $request)
    {
        abort_unless($request->user()->role === UserRole::ORGANIZER, 403);

        $windowMinutes = (int) config('monitoring.confirmation_failure.window_minutes');
        $since = now()->subMinutes($windowMinutes);
        $attempts = ConfirmationAttempt::query()->where('created_at', '>=', $since);
        $total = (clone $attempts)->count();
        $failures = (clone $attempts)->where('succeeded', false)->count();

        return response()->json([
            'data' => [
                'outbox_pending' => OutboxEvent::query()
                    ->where('status', OutboxStatus::PENDING->value)->count(),
                'notification_pending' => NotificationDelivery::query()
                    ->where('status', NotificationStatus::PENDING->value)->count(),
                'notification_failed' => NotificationDelivery::query()
                    ->where('status', NotificationStatus::FAILED->value)->count(),
                'open_alerts' => SystemAlert::query()
                    ->where('status', SystemAlertStatus::OPEN->value)->count(),
                'confirmation_window_minutes' => $windowMinutes,
                'confirmation_attempts' => $total,
                'confirmation_failures' => $failures,
                'confirmation_failure_rate_percent' => $total > 0
                    ? round(($failures / $total) * 100, 2)
                    : 0.0,
                'generated_at' => now()->toISOString(),
            ],
        ]);
    }
}
