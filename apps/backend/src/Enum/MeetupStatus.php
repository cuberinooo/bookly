<?php

namespace App\Enum;

enum MeetupStatus: string
{
    case OPEN = 'open';
    case CONFIRMED = 'confirmed';
    case CANCELLED = 'cancelled';
}
