<?php

namespace App\Application\Reports;

use App\Models\Event;
use Illuminate\Support\Facades\DB;

class EventReportService
{
    public function generate(Event $event): array
    {
        $started = hrtime(true);

        $orders = DB::table('orders')
            ->where('event_id', $event->id)
            ->selectRaw('COUNT(*) AS total_orders')
            ->selectRaw("SUM(CASE WHEN status = 'confirmed' THEN 1 ELSE 0 END) AS confirmed_orders")
            ->selectRaw("SUM(CASE WHEN status = 'cancelled' THEN 1 ELSE 0 END) AS cancelled_orders")
            ->selectRaw('COUNT(DISTINCT buyer_id) AS unique_buyers')
            ->first();

        $tickets = DB::table('tickets')
            ->join('orders', 'orders.id', '=', 'tickets.order_id')
            ->where('orders.event_id', $event->id)
            ->selectRaw('COUNT(*) AS total_tickets')
            ->selectRaw("SUM(CASE WHEN tickets.status = 'active' THEN 1 ELSE 0 END) AS active_tickets")
            ->selectRaw("SUM(CASE WHEN tickets.status = 'cancelled' THEN 1 ELSE 0 END) AS cancelled_tickets")
            ->first();

        $elapsedMs = round((hrtime(true) - $started) / 1_000_000, 2);

        return [
            'event_id' => $event->id,
            'event_name' => $event->name,
            'orders' => [
                'total' => (int) ($orders->total_orders ?? 0),
                'confirmed' => (int) ($orders->confirmed_orders ?? 0),
                'cancelled' => (int) ($orders->cancelled_orders ?? 0),
                'unique_buyers' => (int) ($orders->unique_buyers ?? 0),
            ],
            'tickets' => [
                'total' => (int) ($tickets->total_tickets ?? 0),
                'active' => (int) ($tickets->active_tickets ?? 0),
                'cancelled' => (int) ($tickets->cancelled_tickets ?? 0),
            ],
            'query_time_ms' => $elapsedMs,
            'generated_at' => now()->toISOString(),
        ];
    }
}
