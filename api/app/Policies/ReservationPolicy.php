<?php

namespace App\Policies;

use App\Enums\UserRole;
use App\Models\Reservation;
use App\Models\User;

class ReservationPolicy
{
    public function create(User $user): bool
    {
        return $user->role === UserRole::BUYER;
    }

    public function view(
        User $user,
        Reservation $reservation
    ): bool {
        return $user->role === UserRole::BUYER
            && $reservation->buyer_id === $user->id;
    }

    public function confirm(
        User $user,
        Reservation $reservation
    ): bool {
        return $user->role === UserRole::BUYER
            && $reservation->buyer_id === $user->id;
    }
}
