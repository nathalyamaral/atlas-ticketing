<?php

namespace App\Http\Controllers;

use App\Application\Audit\AuditLogger;
use App\Enums\EventStatus;
use App\Enums\UserRole;
use App\Http\Requests\StoreEventRequest;
use App\Http\Resources\EventResource;
use App\Models\Event;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;

class EventController extends Controller
{
    public function index(Request $request)
    {
        abort_unless($request->user()->role === UserRole::ORGANIZER, 403);

        return EventResource::collection(
            Event::query()
                ->where('organizer_id', $request->user()->id)
                ->latest('id')
                ->paginate(50)
        );
    }

    public function store(StoreEventRequest $request, AuditLogger $audit)
    {
        Gate::authorize('create', Event::class);

        $event = DB::transaction(function () use ($request, $audit) {
            $event = $request->user()->events()->create([
                ...$request->validated(),
                'status' => $request->validated('status') ?? EventStatus::DRAFT->value,
            ]);

            $audit->record('event.created', 'event', $event->id, $event->id, $request->user(), [
                'name' => $event->name,
                'status' => $event->status->value,
            ]);

            return $event;
        });

        return (new EventResource($event))->response()->setStatusCode(201);
    }
}
