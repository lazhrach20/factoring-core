<?php

declare(strict_types=1);

namespace App\Controller\Dev;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/api/test')]
class ApiKeyTestController extends AbstractController
{
    /**
     * Test endpoint to verify API key authentication
     * Can be accessed with X-API-Key header or JWT token
     */
    #[Route('/api-key', name: 'api_test_api_key', methods: ['GET'])]
    #[IsGranted('IS_AUTHENTICATED_FULLY')]
    public function testApiKey(): JsonResponse
    {
        $user = $this->getUser();

        return $this->json([
            'message' => 'API key authentication successful',
            'authenticated' => true,
            'user' => [
                'id' => $user->getId()->toRfc4122(),
                'email' => $user->getEmail(),
                'fullName' => $user->getFullName(),
                'company' => [
                    'id' => $user->getCompany()->getId()->toRfc4122(),
                    'name' => $user->getCompany()->getName(),
                ],
            ],
            'timestamp' => (new \DateTimeImmutable())->format('Y-m-d H:i:s'),
        ]);
    }
}
