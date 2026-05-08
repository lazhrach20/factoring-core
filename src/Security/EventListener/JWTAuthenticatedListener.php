<?php

declare(strict_types=1);

namespace App\Security\EventListener;

use App\Identity\Entity\User;
use Lexik\Bundle\JWTAuthenticationBundle\Event\JWTAuthenticatedEvent;
use Symfony\Component\Security\Core\Exception\CustomUserMessageAuthenticationException;

/**
 * Event listener to validate security_version in JWT
 */
class JWTAuthenticatedListener
{
    /**
     * Validate that JWT security_version matches current user's security_version
     * If versions don't match, token is invalidated (e.g., after password change)
     */
    public function onJWTAuthenticated(JWTAuthenticatedEvent $event): void
    {
        $payload = $event->getPayload();
        $user = $event->getToken()->getUser();

        if (!$user instanceof User) {
            return;
        }

        // Check if security_version exists in token (backwards compatibility)
        if (!isset($payload['security_version'])) {
            // Old tokens without security_version - allow them
            return;
        }

        $tokenSecurityVersion = (int) $payload['security_version'];
        $currentSecurityVersion = $user->getSecurityVersion();

        if ($tokenSecurityVersion !== $currentSecurityVersion) {
            throw new CustomUserMessageAuthenticationException(
                'Token has been invalidated. Please log in again.'
            );
        }
    }
}
