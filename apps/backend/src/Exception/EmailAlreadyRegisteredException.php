<?php

declare(strict_types=1);

namespace App\Exception;

class EmailAlreadyRegisteredException extends \Exception
{
    public function __construct(string $message = 'Email already registered', int $code = 0, ?\Throwable $previous = null)
    {
        parent::__construct($message, $code, $previous);
    }
}
