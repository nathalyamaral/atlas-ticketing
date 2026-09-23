<?php

namespace App\Enums;

enum SeatStatus: string
{
    case AVAILABLE = 'available';
    case RESERVED = 'reserved';
    case SOLD = 'sold';
}
