<?php

declare(strict_types=1);

namespace App\Service;

use Symfony\Contracts\Translation\TranslatorInterface;

class PasswordValidator
{
    public function __construct(
        private TranslatorInterface $translator
    ) {
    }

    /**
     * @throws \Exception
     */
    public function validate(string $password): void
    {
        if (strlen($password) < 8) {
            throw new \Exception($this->translator->trans('error.password_min_length'));
        }
        if (!preg_match('/[A-Z]/', $password)) {
            throw new \Exception($this->translator->trans('error.password_uppercase'));
        }
        if (!preg_match('/[a-z]/', $password)) {
            throw new \Exception($this->translator->trans('error.password_lowercase'));
        }
        if (!preg_match('/[0-9]/', $password)) {
            throw new \Exception($this->translator->trans('error.password_number'));
        }
        if (!preg_match('/[^A-Za-z0-9]/', $password)) {
            throw new \Exception($this->translator->trans('error.password_special'));
        }
    }
}
