<?php

namespace App\Enums;

enum ReservationStatus: string
{
    case ACTIVE = 'active';
    case CONFIRMED = 'confirmed';
    case EXPIRED = 'expired';
    case CANCELLED = 'cancelled';
}
