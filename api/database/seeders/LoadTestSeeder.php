<?php

namespace Database\Seeders;

use App\Enums\EventStatus;
use App\Enums\SeatStatus;
use App\Enums\UserRole;
use App\Models\Event;
use App\Models\Seat;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

class LoadTestSeeder extends Seeder
{
    public function run(): void
    {
        DB::transaction(function () {
            $organizer = User::updateOrCreate(
                ['email' => 'loadtest-organizer@atlas.test'],
                [
                    'name' => 'Load Test Organizer',
                    'role' => UserRole::ORGANIZER->value,
                    'password' => Hash::make('Password123!'),
                ]
            );

            $existingEvent = Event::query()
                ->where('name', 'Flash Sale Load Test')
                ->where('organizer_id', $organizer->id)
                ->first();

            if ($existingEvent) {
                $reservationIds = DB::table('reservations')
                    ->where('event_id', $existingEvent->id)
                    ->pluck('id');
                $orderIds = DB::table('orders')
                    ->where('event_id', $existingEvent->id)
                    ->pluck('id');
                $outboxIds = $orderIds->isEmpty()
                    ? collect()
                    : DB::table('outbox_events')
                        ->where('aggregate_type', 'order')
                        ->whereIn('aggregate_id', $orderIds)
                        ->pluck('id');

                if ($outboxIds->isNotEmpty()) {
                    DB::table('notification_deliveries')->whereIn('outbox_event_id', $outboxIds)->delete();
                    DB::table('outbox_events')->whereIn('id', $outboxIds)->delete();
                }
                if ($orderIds->isNotEmpty()) {
                    DB::table('tickets')->whereIn('order_id', $orderIds)->delete();
                }
                if ($reservationIds->isNotEmpty()) {
                    DB::table('reservation_items')->whereIn('reservation_id', $reservationIds)->delete();
                    DB::table('confirmation_attempts')->whereIn('reservation_id', $reservationIds)->delete();
                }

                DB::table('audit_logs')->where('event_id', $existingEvent->id)->delete();
                DB::table('seats')->where('event_id', $existingEvent->id)->delete();
                DB::table('orders')->where('event_id', $existingEvent->id)->delete();
                DB::table('reservations')->where('event_id', $existingEvent->id)->delete();
                DB::table('events')->where('id', $existingEvent->id)->delete();
            }

            $event = Event::query()->create([
                'organizer_id' => $organizer->id,
                'name' => 'Flash Sale Load Test',
                'location' => 'Campo Grande - MS',
                'sales_start_at' => now()->subMinute(),
                'sales_end_at' => now()->addHour(),
                'starts_at' => now()->addDay(),
                'status' => EventStatus::PUBLISHED->value,
            ]);

            foreach (range(1, 10) as $number) {
                Seat::query()->create([
                    'event_id' => $event->id,
                    'sector' => 'A',
                    'row_label' => 'A',
                    'number' => (string) $number,
                    'status' => SeatStatus::AVAILABLE->value,
                ]);
            }

            foreach (range(1, 50) as $number) {
                User::updateOrCreate(
                    ['email' => sprintf('loadtest-buyer-%02d@atlas.test', $number)],
                    [
                        'name' => "Load Test Buyer {$number}",
                        'role' => UserRole::BUYER->value,
                        'password' => Hash::make('Password123!'),
                    ]
                );
            }

            $this->command?->info("Load test event ID: {$event->id}");
        });
    }
}
