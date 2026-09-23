<?php

namespace App\Application\Tickets;

use App\Application\Audit\AuditLogger;
use App\Enums\OrderStatus;
use App\Enums\TicketStatus;
use App\Exceptions\TicketConflictException;
use App\Models\Order;
use App\Models\Ticket;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class ReissueTicket
{
    public function __construct(private readonly AuditLogger $audit)
    {
    }

    public function execute(User $buyer, Ticket $ticket): Ticket
    {
        return DB::transaction(function () use ($buyer, $ticket) {
            $order = Order::query()
                ->whereKey($ticket->order_id)
                ->lockForUpdate()
                ->firstOrFail();

            $ticket = Ticket::query()
                ->whereKey($ticket->id)
                ->lockForUpdate()
                ->firstOrFail();

            if ($order->buyer_id !== $buyer->id) {
                throw new TicketConflictException('Ticket does not belong to buyer.');
            }

            if ($order->status !== OrderStatus::CONFIRMED) {
                throw new TicketConflictException('Only tickets from confirmed purchases can be reissued.');
            }

            if ($ticket->status !== TicketStatus::ACTIVE) {
                throw new TicketConflictException('Only active tickets can be reissued.');
            }

            $fromVersion = $ticket->version;
            $ticket->update([
                'code' => (string) Str::uuid(),
                'version' => $fromVersion + 1,
                'issued_at' => now(),
            ]);

            $this->audit->record(
                'ticket.reissued',
                'ticket',
                $ticket->id,
                $order->event_id,
                $buyer,
                [
                    'order_id' => $order->id,
                    'seat_id' => $ticket->seat_id,
                    'from_version' => $fromVersion,
                    'to_version' => $fromVersion + 1,
                ],
            );

            return $ticket->fresh()->load('seat');
        }, 3);
    }
}
