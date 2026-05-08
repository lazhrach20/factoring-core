<?php

declare(strict_types=1);

namespace App\Security\EventListener;

use App\Shared\Entity\ApiKey;
use App\Shared\Repository\ApiKeyRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\Security\Http\Event\LoginSuccessEvent;

/**
 * Update API key last_used_at timestamp after successful authentication
 */
class ApiKeyUsageListener implements EventSubscriberInterface
{
    public function __construct(
        private readonly ApiKeyRepository $apiKeyRepository,
        private readonly EntityManagerInterface $entityManager
    ) {
    }

    public static function getSubscribedEvents(): array
    {
        return [
            LoginSuccessEvent::class => 'onLoginSuccess',
        ];
    }

    public function onLoginSuccess(LoginSuccessEvent $event): void
    {
        $request = $event->getRequest();

        // Check if authenticated via API key
        if (!$request->headers->has('X-API-Key')) {
            return;
        }

        // Note: The API key was already validated in ApiKeyAuthenticator
        // Here we just flush the markAsUsed() change
        $this->entityManager->flush();
    }
}
