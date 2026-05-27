<?php

declare(strict_types=1);

namespace App\Enum;

enum MeetupStatus: string
{
    case OPEN = 'open';
    case CONFIRMED = 'confirmed';
    case CANCELLED = 'cancelled';
}
