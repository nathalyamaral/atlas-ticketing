<?php

namespace App\Enums;

enum SystemAlertStatus: string
{
    case OPEN = 'open';
    case RESOLVED = 'resolved';
}
