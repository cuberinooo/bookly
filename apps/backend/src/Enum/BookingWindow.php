<?php

declare(strict_types=1);

namespace App\Enum;

enum BookingWindow: string
{
    case OFF = 'off';
    case CURRENT_WEEK = 'current_week';
    case TWO_WEEKS = 'two_weeks';
    case MONTH = 'month';
}
