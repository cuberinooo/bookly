<?php

declare(strict_types=1);

namespace App\Enum;

enum CourseStatus: string
{
    case ACTIVE = 'active';
    case POSTPONED = 'postponed';
    case DELETED = 'deleted';
}
