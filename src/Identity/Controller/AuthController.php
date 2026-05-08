<?php

declare(strict_types=1);

namespace App\Identity\Controller;

use App\Identity\Dto\LoginRequest;
use App\Identity\Entity\User;
use App\Identity\Service\AuthService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Attribute\MapRequestPayload;
use Symfony\Component\HttpKernel\Exception\UnauthorizedHttpException;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Http\Attribute\CurrentUser;

#[Route('/api/auth', name: 'api_auth_')]
class AuthController extends AbstractController
{
    public function __construct(
        private readonly AuthService $authService
    ) {
    }

    /**
     * Login endpoint - authenticate user and return JWT tokens
     */
    #[Route('/login', name: 'login', methods: ['POST'])]
    public function login(
        #[MapRequestPayload(acceptFormat: 'json', validationFailedStatusCode: 400)] LoginRequest $loginRequest,
        Request $request
    ): JsonResponse {
        try {
            $result = $this->authService->login(
                $loginRequest->getEmail(),
                $loginRequest->getPassword(),
                $request->getClientIp() ?? '0.0.0.0'
            );

            return $this->json([
                'message' => 'Успешная авторизация',
                'accessToken' => $result['accessToken'],
                'refreshToken' => $result['refreshToken'],
                'user' => $result['user'],
            ]);
        } catch (UnauthorizedHttpException $e) {
            return $this->json([
                'error' => $e->getMessage(),
            ], 401);
        }
    }

    /**
     * Get current authenticated user info
     */
    #[Route('/me', name: 'me', methods: ['GET'])]
    public function me(#[CurrentUser] ?User $user): JsonResponse
    {
        if (!$user) {
            return $this->json([
                'error' => 'Требуется авторизация',
            ], 401);
        }

        return $this->json($this->authService->getUserInfo($user));
    }

    /**
     * Refresh token endpoint
     * For Stage 2, this is a simple implementation
     * Proper refresh token logic will be added in Stage 7
     */
    #[Route('/refresh', name: 'refresh', methods: ['POST'])]
    public function refresh(#[CurrentUser] ?User $user): JsonResponse
    {
        if (!$user) {
            return $this->json([
                'error' => 'Недействительный refresh token',
            ], 401);
        }

        // For now, just return user info
        // Proper refresh implementation in Stage 7
        return $this->json([
            'message' => 'Token refresh (будет реализовано в Stage 7)',
            'user' => $this->authService->getUserInfo($user),
        ]);
    }

    /**
     * Logout endpoint
     * For Stage 2, this is a placeholder
     * Proper logout with token blacklist will be added in Stage 7
     */
    #[Route('/logout', name: 'logout', methods: ['POST'])]
    public function logout(): JsonResponse
    {
        return $this->json([
            'message' => 'Logout successful (токен остается валидным до истечения TTL)',
        ]);
    }
}
