<?php

namespace App\Policies;

use App\Enums\UserRole;
use App\Models\Event;
use App\Models\User;

class EventPolicy
{
    public function create(User $user): bool
    {
        return $user->role === UserRole::ORGANIZER;
    }

    public function update(User $user, Event $event): bool
    {
        return $user->role === UserRole::ORGANIZER
            && $event->organizer_id === $user->id;
    }

    public function manageSeats(User $user, Event $event): bool
    {
        return $this->update($user, $event);
    }

    public function viewSeats(User $user, Event $event): bool
    {
        if ($user->role === UserRole::BUYER) {
            return true;
        }

        return $user->role === UserRole::ORGANIZER
            && $event->organizer_id === $user->id;
    }

    public function viewReport(User $user, Event $event): bool
    {
        return $this->update($user, $event);
    }
}
