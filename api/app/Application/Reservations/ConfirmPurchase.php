<?php

namespace App\Application\Reservations;

use App\Application\Audit\AuditLogger;
use App\Enums\NotificationStatus;
use App\Enums\OrderStatus;
use App\Enums\OutboxStatus;
use App\Enums\ReservationStatus;
use App\Enums\SeatStatus;
use App\Enums\TicketStatus;
use App\Exceptions\ReservationConflictException;
use App\Models\NotificationDelivery;
use App\Models\Order;
use App\Models\OutboxEvent;
use App\Models\Reservation;
use App\Models\ReservationItem;
use App\Models\Seat;
use App\Models\Ticket;
use App\Models\User;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class ConfirmPurchase
{
    public function __construct(private readonly AuditLogger $audit)
    {
    }

    public function execute(
        User $buyer,
        Reservation $reservation,
        string $cpf,
        ?string $idempotencyKey = null,
    ): ConfirmPurchaseResult {
        try {
            return DB::transaction(function () use ($buyer, $reservation, $cpf, $idempotencyKey) {
                $now = now();
                $reservation = Reservation::query()->whereKey($reservation->id)->lockForUpdate()->firstOrFail();

                if ($reservation->buyer_id !== $buyer->id) {
                    throw new ReservationConflictException('Reservation does not belong to buyer.');
                }

                if ($idempotencyKey !== null) {
                    $orderByKey = Order::query()->where('idempotency_key', $idempotencyKey)->first();
                    if ($orderByKey && $orderByKey->reservation_id !== $reservation->id) {
                        throw new ReservationConflictException('Idempotency key is already in use.');
                    }
                }

                $existingOrder = Order::query()->where('reservation_id', $reservation->id)->first();
                if ($existingOrder) {
                    return new ConfirmPurchaseResult($existingOrder->load('tickets.seat'), false);
                }

                if ($reservation->status !== ReservationStatus::ACTIVE) {
                    throw new ReservationConflictException('Reservation cannot be confirmed.');
                }

                if ($reservation->expires_at->lte($now)) {
                    throw new ReservationConflictException('Reservation has expired.');
                }

                $seatIds = ReservationItem::query()
                    ->where('reservation_id', $reservation->id)
                    ->orderBy('seat_id')
                    ->pluck('seat_id');

                if ($seatIds->isEmpty()) {
                    throw new ReservationConflictException('Reservation has no seats.');
                }

                $seats = Seat::query()
                    ->whereIn('id', $seatIds)
                    ->orderBy('id')
                    ->lockForUpdate()
                    ->get();

                if ($seats->count() !== $seatIds->count()) {
                    throw new ReservationConflictException('Reservation seats are invalid.');
                }

                foreach ($seats as $seat) {
                    if ($seat->status !== SeatStatus::RESERVED
                        || $seat->reservation_id !== $reservation->id
                        || $seat->reserved_until === null
                        || $seat->reserved_until->lte($now)) {
                        throw new ReservationConflictException('One or more reserved seats are no longer available.');
                    }
                }

                $order = Order::query()->create([
                    'reservation_id' => $reservation->id,
                    'buyer_id' => $buyer->id,
                    'event_id' => $reservation->event_id,
                    'idempotency_key' => $idempotencyKey,
                    'status' => OrderStatus::CONFIRMED->value,
                    'confirmed_at' => $now,
                ]);

                foreach ($seats as $seat) {
                    Ticket::query()->create([
                        'order_id' => $order->id,
                        'seat_id' => $seat->id,
                        'code' => (string) Str::uuid(),
                        'buyer_cpf' => $cpf,
                        'status' => TicketStatus::ACTIVE->value,
                        'version' => 1,
                        'issued_at' => $now,
                    ]);
                }

                Seat::query()
                    ->whereIn('id', $seatIds)
                    ->where('reservation_id', $reservation->id)
                    ->update([
                        'status' => SeatStatus::SOLD->value,
                        'reservation_id' => null,
                        'order_id' => $order->id,
                        'reserved_until' => null,
                        'updated_at' => $now,
                    ]);

                $reservation->update([
                    'status' => ReservationStatus::CONFIRMED->value,
                    'confirmed_at' => $now,
                ]);

                $outboxEvent = OutboxEvent::query()->create([
                    'event_id' => (string) Str::uuid(),
                    'type' => 'purchase.confirmed',
                    'aggregate_type' => 'order',
                    'aggregate_id' => $order->id,
                    'payload' => ['order_id' => $order->id],
                    'status' => OutboxStatus::PENDING->value,
                    'available_at' => $now,
                ]);

                NotificationDelivery::query()->create([
                    'outbox_event_id' => $outboxEvent->id,
                    'notification_id' => (string) Str::uuid(),
                    'status' => NotificationStatus::PENDING->value,
                    'attempts' => 0,
                ]);

                $this->audit->record(
                    'order.confirmed',
                    'order',
                    $order->id,
                    $order->event_id,
                    $buyer,
                    ['reservation_id' => $reservation->id, 'seat_ids' => $seatIds->values()->all()],
                );

                return new ConfirmPurchaseResult($order->load('tickets.seat'), true);
            }, 3);
        } catch (QueryException $exception) {
            if ((int) ($exception->errorInfo[1] ?? 0) === 1062) {
                throw new ReservationConflictException('Purchase confirmation conflicts with an existing request.');
            }
            throw $exception;
        }
    }
}
