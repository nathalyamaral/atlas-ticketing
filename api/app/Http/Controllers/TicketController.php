<?php

namespace App\Http\Controllers;

use App\Application\Tickets\ReissueTicket;
use App\Exceptions\TicketConflictException;
use App\Http\Resources\TicketResource;
use App\Models\Ticket;
use Illuminate\Support\Facades\Gate;

class TicketController extends Controller
{
    public function reissue(Ticket $ticket, ReissueTicket $reissueTicket)
    {
        Gate::authorize('reissue', $ticket);

        try {
            $ticket = $reissueTicket->execute(request()->user(), $ticket);
        } catch (TicketConflictException $exception) {
            return response()->json(['message' => $exception->getMessage()], 409);
        }

        return new TicketResource($ticket);
    }
}
