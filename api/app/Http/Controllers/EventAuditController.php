<?php

namespace App\Http\Controllers;

use App\Models\AuditLog;
use App\Models\Event;
use Illuminate\Support\Facades\Gate;

class EventAuditController extends Controller
{
    public function index(Event $event)
    {
        Gate::authorize('viewReport', $event);

        return response()->json([
            'data' => AuditLog::query()
                ->where('event_id', $event->id)
                ->latest('id')
                ->paginate(100),
        ]);
    }
}
