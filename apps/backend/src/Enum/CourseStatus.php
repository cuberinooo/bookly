<?php

declare(strict_types=1);

namespace App\Enum;

enum CourseStatus: string
{
    case ACTIVE = 'active';
    case CANCELLED = 'cancelled';
    case DELETED = 'deleted';
}
