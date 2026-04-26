<?php

namespace App\Service;

class PasswordValidator
{
    /**
     * @throws \Exception
     */
    public function validate(string $password): void
    {
        if (strlen($password) < 8) {
            throw new \Exception('Password must be at least 8 characters long');
        }
        if (!preg_match('/[A-Z]/', $password)) {
            throw new \Exception('Password must contain at least one uppercase letter');
        }
        if (!preg_match('/[a-z]/', $password)) {
            throw new \Exception('Password must contain at least one lowercase letter');
        }
        if (!preg_match('/[0-9]/', $password)) {
            throw new \Exception('Password must contain at least one number');
        }
        if (!preg_match('/[^A-Za-z0-9]/', $password)) {
            throw new \Exception('Password must contain at least one special character');
        }
    }
}
