<?php

namespace App\Policies;

use App\Enums\UserRole;
use App\Models\Ticket;
use App\Models\User;

class TicketPolicy
{
    public function view(User $user, Ticket $ticket): bool
    {
        return $user->role === UserRole::BUYER
            && $ticket->order()->where('buyer_id', $user->id)->exists();
    }

    public function reissue(User $user, Ticket $ticket): bool
    {
        return $this->view($user, $ticket);
    }
}
