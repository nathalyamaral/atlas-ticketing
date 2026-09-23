<?php

namespace App\Enums;

enum OrderStatus: string
{
    case CONFIRMED = 'confirmed';
    case CANCELLED = 'cancelled';
}
