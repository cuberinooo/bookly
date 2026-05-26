<?php

declare(strict_types=1);

namespace App\Enum;

enum CourseFrequency: string
{
    case DAILY = 'daily';
    case WEEKLY = 'weekly';
    case MONTHLY = 'monthly';
    case WEEKDAYS = 'weekdays';
    case ONCE = 'once';
}
