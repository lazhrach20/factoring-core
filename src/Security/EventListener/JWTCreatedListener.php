<?php

declare(strict_types=1);

namespace App\Security\EventListener;

use App\Identity\Entity\User;
use Lexik\Bundle\JWTAuthenticationBundle\Event\JWTCreatedEvent;

/**
 * Event listener to add security_version to JWT payload
 */
class JWTCreatedListener
{
    /**
     * Add security_version to JWT payload
     * This allows invalidating all tokens when security_version is incremented
     */
    public function onJWTCreated(JWTCreatedEvent $event): void
    {
        $user = $event->getUser();

        if (!$user instanceof User) {
            return;
        }

        $payload = $event->getData();
        $payload['security_version'] = $user->getSecurityVersion();

        $event->setData($payload);
    }
}
