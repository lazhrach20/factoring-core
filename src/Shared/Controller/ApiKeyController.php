<?php

declare(strict_types=1);

namespace App\Shared\Controller;

use App\Shared\Repository\ApiKeyRepository;
use App\Shared\Service\ApiKeyGenerator;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Symfony\Component\Uid\Uuid;

#[Route('/api/api-keys')]
#[IsGranted('IS_AUTHENTICATED_FULLY')]
class ApiKeyController extends AbstractController
{
    public function __construct(
        private readonly ApiKeyGenerator $apiKeyGenerator,
        private readonly ApiKeyRepository $apiKeyRepository
    ) {
    }

    /**
     * Get all API keys for current user
     * Access: Supplier (ROLE_MANAGER+), Debtor (ROLE_ACCOUNTANT+), Factor (ROLE_ADMIN only)
     */
    #[Route('', name: 'api_keys_list', methods: ['GET'])]
    #[IsGranted('API_KEY_MANAGE')]
    public function list(): JsonResponse
    {
        $user = $this->getUser();
        $apiKeys = $this->apiKeyRepository->findByUser($user);

        $data = array_map(function ($apiKey) {
            return [
                'id' => $apiKey->getId()->toRfc4122(),
                'name' => $apiKey->getName(),
                'keyPrefix' => $apiKey->getKeyPrefix(),
                'maskedKey' => $this->maskKey($apiKey->getKeyPrefix()),
                'scopes' => $apiKey->getScopes(),
                'ipWhitelist' => $apiKey->getIpWhitelist(),
                'rateLimit' => $apiKey->getRateLimit(),
                'isActive' => $apiKey->isActive(),
                'lastUsedAt' => $apiKey->getLastUsedAt()?->format('Y-m-d H:i:s'),
                'createdAt' => $apiKey->getCreatedAt()->format('Y-m-d H:i:s'),
                'expiresAt' => $apiKey->getExpiresAt()?->format('Y-m-d H:i:s'),
                'revokedAt' => $apiKey->getRevokedAt()?->format('Y-m-d H:i:s'),
            ];
        }, $apiKeys);

        return $this->json([
            'apiKeys' => $data,
            'total' => count($data),
        ]);
    }

    /**
     * Create a new API key
     * Access: Supplier (ROLE_MANAGER+), Debtor (ROLE_ACCOUNTANT+), Factor (ROLE_ADMIN only)
     */
    #[Route('', name: 'api_keys_create', methods: ['POST'])]
    #[IsGranted('API_KEY_MANAGE')]
    public function create(Request $request): JsonResponse
    {
        $data = json_decode($request->getContent(), true);

        // Validation
        if (empty($data['name'])) {
            return $this->json(['error' => 'Name is required'], Response::HTTP_BAD_REQUEST);
        }

        $name = $data['name'];
        $scopes = $data['scopes'] ?? [];
        $ipWhitelist = !empty($data['ipWhitelist']) ? $data['ipWhitelist'] : null;
        $rateLimit = $data['rateLimit'] ?? 1000;
        $isTest = $data['isTest'] ?? false;

        // Validate scopes
        $validScopes = [
            'accounts:read',
            'accounts:write',
            'transactions:read',
            'transactions:write',
            'users:read',
            'companies:read',
        ];

        foreach ($scopes as $scope) {
            if (!in_array($scope, $validScopes, true)) {
                return $this->json([
                    'error' => "Invalid scope: {$scope}",
                    'validScopes' => $validScopes,
                ], Response::HTTP_BAD_REQUEST);
            }
        }

        // Generate API key
        $result = $this->apiKeyGenerator->generate(
            user: $this->getUser(),
            name: $name,
            scopes: $scopes,
            ipWhitelist: $ipWhitelist,
            rateLimit: $rateLimit,
            isTest: $isTest
        );

        return $this->json([
            'message' => 'API key created successfully',
            'apiKey' => [
                'id' => $result['apiKey']->getId()->toRfc4122(),
                'name' => $result['apiKey']->getName(),
                'scopes' => $result['apiKey']->getScopes(),
                'ipWhitelist' => $result['apiKey']->getIpWhitelist(),
                'rateLimit' => $result['apiKey']->getRateLimit(),
                'createdAt' => $result['apiKey']->getCreatedAt()->format('Y-m-d H:i:s'),
            ],
            'key' => $result['plainKey'], // ⚠️ Only shown ONCE!
            'warning' => 'Save this key securely. It will not be shown again.',
        ], Response::HTTP_CREATED);
    }

    /**
     * Get API key by ID
     * Access: Supplier (ROLE_MANAGER+), Debtor (ROLE_ACCOUNTANT+), Factor (ROLE_ADMIN only)
     */
    #[Route('/{id}', name: 'api_keys_show', methods: ['GET'])]
    #[IsGranted('API_KEY_MANAGE')]
    public function show(string $id): JsonResponse
    {
        try {
            $apiKey = $this->apiKeyRepository->findById(Uuid::fromString($id));

            if (!$apiKey) {
                return $this->json(['error' => 'API key not found'], Response::HTTP_NOT_FOUND);
            }

            // Check ownership
            if ($apiKey->getUser() !== $this->getUser()) {
                return $this->json(['error' => 'Access denied'], Response::HTTP_FORBIDDEN);
            }

            return $this->json([
                'id' => $apiKey->getId()->toRfc4122(),
                'name' => $apiKey->getName(),
                'keyPrefix' => $apiKey->getKeyPrefix(),
                'maskedKey' => $this->maskKey($apiKey->getKeyPrefix()),
                'scopes' => $apiKey->getScopes(),
                'ipWhitelist' => $apiKey->getIpWhitelist(),
                'rateLimit' => $apiKey->getRateLimit(),
                'isActive' => $apiKey->isActive(),
                'lastUsedAt' => $apiKey->getLastUsedAt()?->format('Y-m-d H:i:s'),
                'createdAt' => $apiKey->getCreatedAt()->format('Y-m-d H:i:s'),
                'expiresAt' => $apiKey->getExpiresAt()?->format('Y-m-d H:i:s'),
                'revokedAt' => $apiKey->getRevokedAt()?->format('Y-m-d H:i:s'),
            ]);
        } catch (\InvalidArgumentException $e) {
            return $this->json(['error' => 'Invalid UUID format'], Response::HTTP_BAD_REQUEST);
        }
    }

    /**
     * Revoke (delete) an API key
     * Access: Supplier (ROLE_MANAGER+), Debtor (ROLE_ACCOUNTANT+), Factor (ROLE_ADMIN only)
     */
    #[Route('/{id}', name: 'api_keys_revoke', methods: ['DELETE'])]
    #[IsGranted('API_KEY_MANAGE')]
    public function revoke(string $id): JsonResponse
    {
        try {
            $apiKey = $this->apiKeyRepository->findById(Uuid::fromString($id));

            if (!$apiKey) {
                return $this->json(['error' => 'API key not found'], Response::HTTP_NOT_FOUND);
            }

            // Check ownership
            if ($apiKey->getUser() !== $this->getUser()) {
                return $this->json(['error' => 'Access denied'], Response::HTTP_FORBIDDEN);
            }

            // Revoke the key
            $apiKey->revoke();
            $this->apiKeyRepository->save($apiKey, flush: true);

            return $this->json([
                'message' => 'API key revoked successfully',
                'apiKey' => [
                    'id' => $apiKey->getId()->toRfc4122(),
                    'name' => $apiKey->getName(),
                    'revokedAt' => $apiKey->getRevokedAt()->format('Y-m-d H:i:s'),
                ],
            ]);
        } catch (\InvalidArgumentException $e) {
            return $this->json(['error' => 'Invalid UUID format'], Response::HTTP_BAD_REQUEST);
        }
    }

    /**
     * Mask key for display (show only prefix and last 4 chars)
     */
    private function maskKey(string $keyPrefix): string
    {
        return $keyPrefix . str_repeat('*', 24) . '****';
    }
}
