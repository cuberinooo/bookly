<?php

declare(strict_types=1);

namespace App\Exception;

class ScheduleConflictException extends \Exception
{
    public function getFrontendMessage(): string
    {
        return $this->getMessage();
    }
}
