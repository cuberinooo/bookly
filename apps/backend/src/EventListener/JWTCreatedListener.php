<?php

namespace App\EventListener;

use App\Entity\User;
use Lexik\Bundle\JWTAuthenticationBundle\Event\JWTCreatedEvent;

class JWTCreatedListener
{
    public function onJWTCreated(JWTCreatedEvent $event): void
    {
        $user = $event->getUser();

        if (!$user instanceof User) {
            return;
        }

        $payload = $event->getData();
        $payload['id'] = $user->getId();
        $payload['name'] = $user->getName();
        $payload['isActive'] = $user->isActive();
        $payload['mustChangePassword'] = $user->isMustChangePassword();
        $payload['companyId'] = $user->getCompany() ? $user->getCompany()->getId() : null;
        $payload['companyName'] = $user->getCompany() ? $user->getCompany()->getName() : null;

        $event->setData($payload);
    }
}
