<?php

namespace App\Twig;

use App\Entity\User;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;
use Twig\Extension\AbstractExtension;
use Twig\Extension\GlobalsInterface;

class GlobalSettingsExtension extends AbstractExtension implements GlobalsInterface
{
    public function __construct(
        private TokenStorageInterface $tokenStorage
    ) {}

    public function getGlobals(): array
    {
        $token = $this->tokenStorage->getToken();
        $user = $token?->getUser();
        
        $siteName = 'Phoenix Athletics';
        if ($user instanceof User && $user->getCompany()) {
            $siteName = $user->getCompany()->getName();
        }

        return [
            'siteName' => $siteName,
        ];
    }
}
