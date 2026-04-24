<?php

namespace App\Exception;

class ScheduleConflictException extends \Exception
{
    public function getFrontendMessage(): string
    {
        return $this->getMessage();
    }
}
