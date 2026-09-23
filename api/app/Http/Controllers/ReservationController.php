<?php

namespace App\Http\Controllers;

use App\Application\Reservations\ConfirmPurchase;
use App\Application\Reservations\ReserveSeats;
use App\Exceptions\ReservationConflictException;
use App\Http\Requests\ConfirmReservationRequest;
use App\Http\Requests\ReserveSeatsRequest;
use App\Http\Resources\OrderResource;
use App\Http\Resources\ReservationResource;
use App\Models\ConfirmationAttempt;
use App\Models\Event;
use App\Models\Reservation;
use Illuminate\Support\Facades\Gate;
use Throwable;

class ReservationController extends Controller
{
    public function store(ReserveSeatsRequest $request, Event $event, ReserveSeats $reserveSeats)
    {
        Gate::authorize('create', Reservation::class);

        try {
            $reservation = $reserveSeats->execute(
                $request->user(), $event, $request->validated('seat_ids')
            );
        } catch (ReservationConflictException $exception) {
            return response()->json(['message' => $exception->getMessage()], 409);
        }

        return (new ReservationResource($reservation))->response()->setStatusCode(201);
    }

    public function show(Reservation $reservation): ReservationResource
    {
        Gate::authorize('view', $reservation);
        return new ReservationResource($reservation->load('items.seat'));
    }

    public function confirm(
        ConfirmReservationRequest $request,
        Reservation $reservation,
        ConfirmPurchase $confirmPurchase
    ) {
        Gate::authorize('confirm', $reservation);
        $started = hrtime(true);

        try {
            $result = $confirmPurchase->execute(
                $request->user(),
                $reservation,
                $request->validated('cpf'),
                $request->validated('idempotency_key'),
            );

            $this->recordAttempt($reservation, $request->user()->id, true, null, $started);
        } catch (ReservationConflictException $exception) {
            $this->recordAttempt($reservation, $request->user()->id, false, 'conflict', $started);
            return response()->json(['message' => $exception->getMessage()], 409);
        } catch (Throwable $exception) {
            $this->recordAttempt($reservation, $request->user()->id, false, 'internal_error', $started);
            throw $exception;
        }

        return (new OrderResource($result->order))
            ->response()
            ->setStatusCode($result->created ? 201 : 200);
    }

    private function recordAttempt(
        Reservation $reservation,
        int $buyerId,
        bool $succeeded,
        ?string $failureReason,
        int $started,
    ): void {
        try {
            ConfirmationAttempt::query()->create([
                'reservation_id' => $reservation->id,
                'buyer_id' => $buyerId,
                'succeeded' => $succeeded,
                'failure_reason' => $failureReason,
                'duration_ms' => max(0, (int) round((hrtime(true) - $started) / 1_000_000)),
                'created_at' => now(),
            ]);
        } catch (Throwable $exception) {
            // Telemetry must not change the checkout result.
            report($exception);
        }
    }
}
