<?php

namespace App\Enum;

enum CourseFrequency: string
{
    case DAILY = 'daily';
    case WEEKLY = 'weekly';
    case MONTHLY = 'monthly';
    case WEEKDAYS = 'weekdays';
    case ONCE = 'once';
}
