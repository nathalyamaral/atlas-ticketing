<?php

namespace App\Application\Orders;

use App\Application\Audit\AuditLogger;
use App\Enums\OrderStatus;
use App\Enums\ReservationStatus;
use App\Enums\SeatStatus;
use App\Enums\TicketStatus;
use App\Models\Order;
use App\Models\Seat;
use App\Models\Ticket;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class CancelOrder
{
    public function __construct(
        private readonly AuditLogger $audit,
    ) {
    }

    public function execute(User $buyer, Order $order): Order
    {
        return DB::transaction(function () use ($buyer, $order) {
            $order = Order::query()
                ->whereKey($order->id)
                ->lockForUpdate()
                ->firstOrFail();

            if ($order->status === OrderStatus::CANCELLED) {
                return $order->load('tickets.seat');
            }

            $now = now();

            $seatIds = Ticket::query()
                ->where('order_id', $order->id)
                ->orderBy('seat_id')
                ->pluck('seat_id');

            Seat::query()
                ->whereIn('id', $seatIds)
                ->where('order_id', $order->id)
                ->orderBy('id')
                ->lockForUpdate()
                ->get();

            Ticket::query()
                ->where('order_id', $order->id)
                ->where('status', TicketStatus::ACTIVE->value)
                ->update([
                    'status' => TicketStatus::CANCELLED->value,
                    'cancelled_at' => $now,
                    'updated_at' => $now,
                ]);

            Seat::query()
                ->whereIn('id', $seatIds)
                ->where('order_id', $order->id)
                ->update([
                    'status' => SeatStatus::AVAILABLE->value,
                    'order_id' => null,
                    'reservation_id' => null,
                    'reserved_until' => null,
                    'updated_at' => $now,
                ]);

            $order->update([
                'status' => OrderStatus::CANCELLED->value,
                'cancelled_at' => $now,
            ]);

            $order->reservation()->update([
                'status' => ReservationStatus::CANCELLED->value,
                'cancelled_at' => $now,
            ]);

            $this->audit->record(
                'order.cancelled',
                'order',
                $order->id,
                $order->event_id,
                $buyer,
                ['released_seat_ids' => $seatIds->values()->all()],
            );

            return $order->fresh()->load('tickets.seat');
        }, 3);
    }
}
