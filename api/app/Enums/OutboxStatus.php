<?php

namespace App\Enums;

enum OutboxStatus: string
{
    case PENDING = 'pending';
    case PUBLISHED = 'published';
    case FAILED = 'failed';
}
