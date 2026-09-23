<?php

namespace App\Enums;

enum TicketStatus: string
{
    case ACTIVE = 'active';
    case CANCELLED = 'cancelled';
}
