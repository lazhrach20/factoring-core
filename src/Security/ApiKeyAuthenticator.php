<?php

declare(strict_types=1);

namespace App\Security;

use App\Shared\Service\ApiKeyGenerator;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Exception\AuthenticationException;
use Symfony\Component\Security\Core\Exception\CustomUserMessageAuthenticationException;
use Symfony\Component\Security\Http\Authenticator\AbstractAuthenticator;
use Symfony\Component\Security\Http\Authenticator\Passport\Badge\UserBadge;
use Symfony\Component\Security\Http\Authenticator\Passport\Passport;
use Symfony\Component\Security\Http\Authenticator\Passport\SelfValidatingPassport;

class ApiKeyAuthenticator extends AbstractAuthenticator
{
    private const API_KEY_HEADER = 'X-API-Key';

    public function __construct(
        private readonly ApiKeyGenerator $apiKeyGenerator
    ) {
    }

    public function supports(Request $request): ?bool
    {
        return $request->headers->has(self::API_KEY_HEADER);
    }

    public function authenticate(Request $request): Passport
    {
        $apiKeyValue = $request->headers->get(self::API_KEY_HEADER);

        if (!$apiKeyValue) {
            throw new CustomUserMessageAuthenticationException('No API key provided');
        }

        // Validate key format
        if (!$this->apiKeyGenerator->isValidKeyFormat($apiKeyValue)) {
            throw new CustomUserMessageAuthenticationException('Invalid API key format');
        }

        // Find API key
        $apiKey = $this->apiKeyGenerator->findActiveByPlainKey($apiKeyValue);

        if (!$apiKey) {
            throw new CustomUserMessageAuthenticationException('Invalid or revoked API key');
        }

        // Check if key is valid (not expired, not revoked)
        if (!$apiKey->isValid()) {
            throw new CustomUserMessageAuthenticationException('API key has expired or been revoked');
        }

        // Check IP whitelist
        $clientIp = $request->getClientIp();
        if (!$apiKey->isIpAllowed($clientIp)) {
            throw new CustomUserMessageAuthenticationException('IP address not allowed for this API key');
        }

        // Update last used timestamp
        $apiKey->markAsUsed();
        // Note: We'll flush this in a listener to avoid performance issues

        // Return passport with user
        return new SelfValidatingPassport(
            new UserBadge(
                $apiKey->getUser()->getEmail(),
                function () use ($apiKey) {
                    return $apiKey->getUser();
                }
            )
        );
    }

    public function onAuthenticationSuccess(Request $request, TokenInterface $token, string $firewallName): ?Response
    {
        // On success, let the request continue
        return null;
    }

    public function onAuthenticationFailure(Request $request, AuthenticationException $exception): ?Response
    {
        return new JsonResponse([
            'error' => 'Authentication failed',
            'message' => $exception->getMessage(),
        ], Response::HTTP_UNAUTHORIZED);
    }
}
