<?php

namespace App\Enum;

enum CourseStatus: string
{
    case ACTIVE = 'active';
    case POSTPONED = 'postponed';
}
