<?php

namespace App\Application\Reservations;

use App\Application\Audit\AuditLogger;
use App\Enums\EventStatus;
use App\Enums\ReservationStatus;
use App\Enums\SeatStatus;
use App\Exceptions\ReservationConflictException;
use App\Models\Event;
use App\Models\Reservation;
use App\Models\ReservationItem;
use App\Models\Seat;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class ReserveSeats
{
    public function __construct(private readonly AuditLogger $audit)
    {
    }

    public function execute(User $buyer, Event $event, array $seatIds): Reservation
    {
        $this->assertSaleIsOpen($event);
        $seatIds = array_values(array_unique(array_map('intval', $seatIds)));
        sort($seatIds, SORT_NUMERIC);

        return DB::transaction(function () use ($buyer, $event, $seatIds) {
            $now = now();
            $seats = Seat::query()
                ->where('event_id', $event->id)
                ->whereIn('id', $seatIds)
                ->orderBy('id')
                ->lockForUpdate()
                ->get();

            if ($seats->count() !== count($seatIds)) {
                throw new ReservationConflictException('One or more seats are unavailable.');
            }

            if ($seats->contains(fn (Seat $seat) => ! $this->isAvailable($seat, $now))) {
                throw new ReservationConflictException('One or more seats are unavailable.');
            }

            $expiresAt = $now->copy()->addMinutes((int) config('ticketing.reservation_ttl_minutes'));
            $reservation = Reservation::query()->create([
                'buyer_id' => $buyer->id,
                'event_id' => $event->id,
                'status' => ReservationStatus::ACTIVE->value,
                'expires_at' => $expiresAt,
            ]);

            Seat::query()->whereIn('id', $seatIds)->update([
                'status' => SeatStatus::RESERVED->value,
                'reservation_id' => $reservation->id,
                'order_id' => null,
                'reserved_until' => $expiresAt,
                'updated_at' => $now,
            ]);

            ReservationItem::query()->insert(array_map(fn (int $seatId) => [
                'reservation_id' => $reservation->id,
                'seat_id' => $seatId,
                'created_at' => $now,
                'updated_at' => $now,
            ], $seatIds));

            $this->audit->record(
                'reservation.created',
                'reservation',
                $reservation->id,
                $event->id,
                $buyer,
                ['seat_ids' => $seatIds, 'expires_at' => $expiresAt->toISOString()],
            );

            return $reservation->load('items.seat');
        }, 3);
    }

    private function isAvailable(Seat $seat, $now): bool
    {
        if ($seat->status === SeatStatus::AVAILABLE) {
            return true;
        }

        return $seat->status === SeatStatus::RESERVED
            && $seat->reserved_until !== null
            && $seat->reserved_until->lte($now);
    }

    private function assertSaleIsOpen(Event $event): void
    {
        $now = now();

        if ($event->status !== EventStatus::PUBLISHED
            || $event->sales_start_at?->gt($now)
            || ($event->sales_end_at && $event->sales_end_at->lte($now))
            || $event->starts_at?->lte($now)) {
            throw new ReservationConflictException('Event sales are not open.');
        }
    }
}
