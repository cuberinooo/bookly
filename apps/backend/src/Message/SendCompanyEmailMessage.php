<?php

declare(strict_types=1);

namespace App\Message;

use Symfony\Component\Mime\Email;

class SendCompanyEmailMessage
{
    public function __construct(
        public readonly int $companyId,
        public readonly Email $email
    ) {
    }
}
