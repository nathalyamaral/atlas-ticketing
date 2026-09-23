<?php

namespace App\Policies;

use App\Enums\UserRole;
use App\Models\Order;
use App\Models\User;

class OrderPolicy
{
    public function view(User $user, Order $order): bool
    {
        return $user->role === UserRole::BUYER
            && $order->buyer_id === $user->id;
    }

    public function cancel(User $user, Order $order): bool
    {
        return $this->view($user, $order);
    }
}
