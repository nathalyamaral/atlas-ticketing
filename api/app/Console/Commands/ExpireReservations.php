<?php

namespace App\Console\Commands;

use App\Enums\ReservationStatus;
use App\Enums\SeatStatus;
use App\Models\Reservation;
use App\Models\Seat;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class ExpireReservations extends Command
{
    protected $signature = 'reservations:expire';

    protected $description =
        'Expire reservations and release their seats';

    public function handle(): int
    {
        Reservation::query()
            ->where(
                'status',
                ReservationStatus::ACTIVE->value
            )
            ->where('expires_at', '<=', now())
            ->select('id')
            ->chunkById(
                100,
                function ($reservations) {
                    foreach ($reservations as $candidate) {
                        $this->expire(
                            $candidate->id
                        );
                    }
                }
            );

        return self::SUCCESS;
    }

    private function expire(int $reservationId): void
    {
        DB::transaction(function () use (
            $reservationId
        ) {
            $reservation = Reservation::query()
                ->whereKey($reservationId)
                ->lockForUpdate()
                ->first();

            if (
                ! $reservation
                || $reservation->status
                !== ReservationStatus::ACTIVE
                || $reservation->expires_at->isFuture()
            ) {
                return;
            }

            $seatIds = Seat::query()
                ->where(
                    'reservation_id',
                    $reservation->id
                )
                ->orderBy('id')
                ->lockForUpdate()
                ->pluck('id');

            if ($seatIds->isNotEmpty()) {
                Seat::query()
                    ->whereIn('id', $seatIds)
                    ->where(
                        'reservation_id',
                        $reservation->id
                    )
                    ->update([
                        'status' =>
                            SeatStatus::AVAILABLE->value,

                        'reservation_id' => null,
                        'reserved_until' => null,
                        'updated_at' => now(),
                    ]);
            }

            $reservation->update([
                'status' =>
                    ReservationStatus::EXPIRED->value,
            ]);
        }, 3);
    }
}
