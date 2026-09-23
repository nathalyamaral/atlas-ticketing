<?php

namespace App\Http\Controllers;

use App\Enums\UserRole;
use App\Models\SystemAlert;
use Illuminate\Http\Request;

class SystemAlertController extends Controller
{
    public function index(Request $request)
    {
        abort_unless($request->user()->role === UserRole::ORGANIZER, 403);

        return response()->json([
            'data' => SystemAlert::query()
                ->latest('id')
                ->paginate(100),
        ]);
    }
}
