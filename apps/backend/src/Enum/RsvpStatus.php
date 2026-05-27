<?php

declare(strict_types=1);

namespace App\Enum;

enum RsvpStatus: string
{
    case GOING = 'going';
    case NOT_GOING = 'not_going';
}
