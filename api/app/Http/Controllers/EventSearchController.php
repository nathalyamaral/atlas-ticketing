<?php

namespace App\Http\Controllers;

use App\Contracts\EventSearch;
use App\Exceptions\SearchUnavailableException;
use App\Http\Requests\SearchEventsRequest;
use App\Enums\UserRole;
use App\Http\Resources\EventResource;

class EventSearchController extends Controller
{
    public function index(SearchEventsRequest $request, EventSearch $search)
    {
        abort_unless($request->user()->role === UserRole::BUYER, 403);

        try {
            $events = $search->search(
                $request->safe()->except('per_page'),
                (int) ($request->validated('per_page') ?? 50),
            );
        } catch (SearchUnavailableException $exception) {
            return response()->json([
                'message' => $exception->getMessage(),
            ], 503);
        }

        return EventResource::collection($events);
    }
}
