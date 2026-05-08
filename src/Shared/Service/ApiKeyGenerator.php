<?php

declare(strict_types=1);

namespace App\Shared\Service;

use App\Identity\Entity\User;
use App\Shared\Entity\ApiKey;
use App\Shared\Repository\ApiKeyRepository;

class ApiKeyGenerator
{
    private const KEY_LENGTH = 32; // length of random part
    private const PREFIX_LIVE = 'sk_live_';
    private const PREFIX_TEST = 'sk_test_';

    public function __construct(
        private readonly ApiKeyRepository $apiKeyRepository
    ) {
    }

    /**
     * Generate a new API key
     * Returns the plain key (only shown once to user)
     */
    public function generate(
        User $user,
        string $name,
        array $scopes = [],
        ?array $ipWhitelist = null,
        int $rateLimit = 1000,
        bool $isTest = false
    ): array {
        $prefix = $isTest ? self::PREFIX_TEST : self::PREFIX_LIVE;

        // Generate random key
        $randomPart = $this->generateRandomString(self::KEY_LENGTH);
        $plainKey = $prefix . $randomPart;

        // Hash the key for storage
        $keyHash = $this->hashKey($plainKey);

        // Create API key entity
        $apiKey = new ApiKey(
            user: $user,
            name: $name,
            keyHash: $keyHash,
            keyPrefix: $prefix,
            scopes: $scopes,
            ipWhitelist: $ipWhitelist,
            rateLimit: $rateLimit
        );

        $this->apiKeyRepository->save($apiKey, flush: true);

        return [
            'apiKey' => $apiKey,
            'plainKey' => $plainKey, // Only returned once!
        ];
    }

    /**
     * Verify if a plain key matches a stored hash
     */
    public function verifyKey(string $plainKey, string $storedHash): bool
    {
        return hash_equals($storedHash, $this->hashKey($plainKey));
    }

    /**
     * Hash a plain key for storage
     */
    public function hashKey(string $plainKey): string
    {
        return hash('sha256', $plainKey);
    }

    /**
     * Find API key by plain key
     */
    public function findByPlainKey(string $plainKey): ?ApiKey
    {
        $keyHash = $this->hashKey($plainKey);
        return $this->apiKeyRepository->findByKeyHash($keyHash);
    }

    /**
     * Find active API key by plain key
     */
    public function findActiveByPlainKey(string $plainKey): ?ApiKey
    {
        $keyHash = $this->hashKey($plainKey);
        return $this->apiKeyRepository->findActiveByKeyHash($keyHash);
    }

    /**
     * Generate a cryptographically secure random string
     */
    private function generateRandomString(int $length): string
    {
        $bytes = random_bytes((int) ceil($length * 0.75));
        $randomString = rtrim(strtr(base64_encode($bytes), '+/', '-_'), '=');

        return substr($randomString, 0, $length);
    }

    /**
     * Validate key format
     */
    public function isValidKeyFormat(string $key): bool
    {
        return (
            str_starts_with($key, self::PREFIX_LIVE) ||
            str_starts_with($key, self::PREFIX_TEST)
        ) && strlen($key) > 10;
    }

    /**
     * Extract prefix from key
     */
    public function getKeyPrefix(string $key): ?string
    {
        if (str_starts_with($key, self::PREFIX_LIVE)) {
            return self::PREFIX_LIVE;
        }

        if (str_starts_with($key, self::PREFIX_TEST)) {
            return self::PREFIX_TEST;
        }

        return null;
    }
}
