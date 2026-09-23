<?php

namespace App\Http\Controllers;

use App\Application\Audit\AuditLogger;
use App\Enums\SeatStatus;
use App\Http\Requests\StoreSeatsRequest;
use App\Http\Resources\SeatResource;
use App\Models\Event;
use App\Models\Seat;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;

class SeatController extends Controller
{
    public function store(StoreSeatsRequest $request, Event $event, AuditLogger $audit)
    {
        Gate::authorize('manageSeats', $event);
        $now = now();

        $rows = collect($request->validated('seats'))->map(fn (array $seat) => [
            'event_id' => $event->id,
            'sector' => $seat['sector'] ?? 'General',
            'row_label' => $seat['row_label'],
            'number' => $seat['number'],
            'status' => SeatStatus::AVAILABLE->value,
            'created_at' => $now,
            'updated_at' => $now,
        ])->all();

        try {
            DB::transaction(fn () => Seat::query()->insert($rows));
        } catch (QueryException $exception) {
            if ((int) ($exception->errorInfo[1] ?? 0) === 1062) {
                return response()->json(['message' => 'One or more seats already exist.'], 409);
            }
            throw $exception;
        }

        $audit->record('seats.created', 'event', $event->id, $event->id, $request->user(), [
            'count' => count($rows),
        ]);

        return response()->json(['message' => 'Seats created successfully.', 'count' => count($rows)], 201);
    }

    public function index(Event $event)
    {
        Gate::authorize('viewSeats', $event);

        $seats = Seat::query()
            ->where('event_id', $event->id)
            ->where(function ($query) {
                $query->where('status', SeatStatus::AVAILABLE->value)
                    ->orWhere(function ($query) {
                        $query->where('status', SeatStatus::RESERVED->value)
                            ->where('reserved_until', '<=', now());
                    });
            })
            ->orderBy('sector')
            ->orderBy('row_label')
            ->orderBy('number')
            ->paginate(100);

        return SeatResource::collection($seats);
    }
}
