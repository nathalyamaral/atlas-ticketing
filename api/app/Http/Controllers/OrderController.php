<?php

namespace App\Http\Controllers;

use App\Application\Orders\CancelOrder;
use App\Enums\UserRole;
use App\Http\Resources\OrderResource;
use App\Models\Order;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

class OrderController extends Controller
{
    public function index(Request $request)
    {
        abort_unless($request->user()->role === UserRole::BUYER, 403);

        $orders = Order::query()
            ->where('buyer_id', $request->user()->id)
            ->with('tickets.seat')
            ->latest('id')
            ->paginate(50);

        return OrderResource::collection($orders);
    }

    public function show(Order $order): OrderResource
    {
        Gate::authorize('view', $order);

        return new OrderResource($order->load('tickets.seat'));
    }

    public function cancel(Order $order, CancelOrder $cancelOrder): OrderResource
    {
        Gate::authorize('cancel', $order);

        return new OrderResource(
            $cancelOrder->execute(request()->user(), $order)
        );
    }
}
