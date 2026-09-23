<?php

namespace Database\Seeders;

use App\Enums\EventStatus;
use App\Enums\OrderStatus;
use App\Enums\ReservationStatus;
use App\Enums\SeatStatus;
use App\Enums\TicketStatus;
use App\Enums\UserRole;
use App\Models\Event;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

class ReportBenchmarkSeeder extends Seeder
{
    private const ORDER_COUNT = 25000;
    private const TICKETS_PER_ORDER = 8;

    public function run(): void
    {
        $organizer = User::query()->firstOrCreate(
            ['email' => 'report-organizer@atlas.test'],
            ['name' => 'Report Organizer', 'role' => UserRole::ORGANIZER->value, 'password' => Hash::make('Password123!')]
        );
        $buyer = User::query()->firstOrCreate(
            ['email' => 'report-buyer@atlas.test'],
            ['name' => 'Report Buyer', 'role' => UserRole::BUYER->value, 'password' => Hash::make('Password123!')]
        );

        $existing = Event::query()->where('name', 'Report Benchmark 200k')->first();
        if ($existing) {
            DB::transaction(function () use ($existing) {
                $orderIds = DB::table('orders')->where('event_id', $existing->id)->pluck('id');
                $reservationIds = DB::table('reservations')->where('event_id', $existing->id)->pluck('id');
                DB::table('audit_logs')->where('event_id', $existing->id)->delete();
                if ($orderIds->isNotEmpty()) DB::table('tickets')->whereIn('order_id', $orderIds)->delete();
                if ($reservationIds->isNotEmpty()) DB::table('reservation_items')->whereIn('reservation_id', $reservationIds)->delete();
                DB::table('seats')->where('event_id', $existing->id)->delete();
                DB::table('orders')->where('event_id', $existing->id)->delete();
                DB::table('reservations')->where('event_id', $existing->id)->delete();
                DB::table('events')->where('id', $existing->id)->delete();
            });
        }

        $now = now();
        $eventId = DB::table('events')->insertGetId([
            'organizer_id' => $organizer->id,
            'name' => 'Report Benchmark 200k',
            'location' => 'Campo Grande - MS',
            'starts_at' => $now->copy()->addMonth(),
            'sales_start_at' => $now->copy()->subMonth(),
            'sales_end_at' => $now->copy()->addWeeks(3),
            'status' => EventStatus::PUBLISHED->value,
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        $reservationBase = ((int) DB::table('reservations')->max('id')) + 1;
        $orderBase = ((int) DB::table('orders')->max('id')) + 1;
        $seatBase = ((int) DB::table('seats')->max('id')) + 1;
        $ticketBase = ((int) DB::table('tickets')->max('id')) + 1;
        $encryptedCpf = Crypt::encryptString('00000000000');

        foreach (array_chunk(range(0, self::ORDER_COUNT - 1), 500) as $chunk) {
            $reservations = [];
            $orders = [];
            foreach ($chunk as $i) {
                $reservationId = $reservationBase + $i;
                $orderId = $orderBase + $i;
                $reservations[] = [
                    'id' => $reservationId,
                    'buyer_id' => $buyer->id,
                    'event_id' => $eventId,
                    'status' => ReservationStatus::CONFIRMED->value,
                    'expires_at' => $now->copy()->addMinutes(10),
                    'confirmed_at' => $now,
                    'cancelled_at' => null,
                    'created_at' => $now,
                    'updated_at' => $now,
                ];
                $orders[] = [
                    'id' => $orderId,
                    'reservation_id' => $reservationId,
                    'buyer_id' => $buyer->id,
                    'event_id' => $eventId,
                    'idempotency_key' => null,
                    'status' => OrderStatus::CONFIRMED->value,
                    'confirmed_at' => $now,
                    'cancelled_at' => null,
                    'created_at' => $now,
                    'updated_at' => $now,
                ];
            }
            DB::table('reservations')->insert($reservations);
            DB::table('orders')->insert($orders);
        }

        $totalTickets = self::ORDER_COUNT * self::TICKETS_PER_ORDER;
        foreach (array_chunk(range(0, $totalTickets - 1), 2000) as $chunk) {
            $seats = [];
            $tickets = [];
            $items = [];
            foreach ($chunk as $i) {
                $orderIndex = intdiv($i, self::TICKETS_PER_ORDER);
                $reservationId = $reservationBase + $orderIndex;
                $orderId = $orderBase + $orderIndex;
                $seatId = $seatBase + $i;
                $ticketId = $ticketBase + $i;
                $uuid = sprintf('00000000-0000-4000-8000-%012d', $ticketId % 1000000000000);

                $seats[] = [
                    'id' => $seatId,
                    'event_id' => $eventId,
                    'reservation_id' => null,
                    'order_id' => $orderId,
                    'sector' => 'General',
                    'row_label' => 'R' . intdiv($i, 1000),
                    'number' => (string) ($i + 1),
                    'status' => SeatStatus::SOLD->value,
                    'reserved_until' => null,
                    'created_at' => $now,
                    'updated_at' => $now,
                ];
                $tickets[] = [
                    'id' => $ticketId,
                    'order_id' => $orderId,
                    'seat_id' => $seatId,
                    'code' => $uuid,
                    'buyer_cpf' => $encryptedCpf,
                    'status' => TicketStatus::ACTIVE->value,
                    'version' => 1,
                    'issued_at' => $now,
                    'cancelled_at' => null,
                    'created_at' => $now,
                    'updated_at' => $now,
                ];
                $items[] = [
                    'reservation_id' => $reservationId,
                    'seat_id' => $seatId,
                    'created_at' => $now,
                    'updated_at' => $now,
                ];
            }
            DB::table('seats')->insert($seats);
            DB::table('tickets')->insert($tickets);
            DB::table('reservation_items')->insert($items);
        }

        $this->command?->info("Report benchmark event ID: {$eventId}");
        $this->command?->info('Seeded 200,000 tickets.');
    }
}
