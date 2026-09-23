<?php

namespace App\Http\Controllers;

use App\Application\Reports\EventReportService;
use App\Models\Event;
use Illuminate\Support\Facades\Gate;

class EventReportController extends Controller
{
    public function show(Event $event, EventReportService $reports)
    {
        Gate::authorize('viewReport', $event);

        return response()->json([
            'data' => $reports->generate($event),
        ]);
    }
}
