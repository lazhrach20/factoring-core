<?php

declare(strict_types=1);

namespace App\Identity\Service;

use App\Identity\Entity\User;
use App\Identity\Repository\UserRepository;
use Lexik\Bundle\JWTAuthenticationBundle\Services\JWTTokenManagerInterface;
use Symfony\Component\HttpKernel\Exception\UnauthorizedHttpException;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

/**
 * Service for authentication operations
 */
class AuthService
{
    public function __construct(
        private readonly UserRepository $userRepository,
        private readonly UserPasswordHasherInterface $passwordHasher,
        private readonly JWTTokenManagerInterface $jwtManager
    ) {
    }

    /**
     * Authenticate user and generate JWT tokens
     *
     * @return array{accessToken: string, refreshToken: string, user: array}
     */
    public function login(string $email, string $password, string $ip): array
    {
        $user = $this->userRepository->findByEmail($email);

        if (!$user) {
            throw new UnauthorizedHttpException('', 'Неверный email или пароль');
        }

        if ($user->isBlocked()) {
            throw new UnauthorizedHttpException('', 'Пользователь заблокирован: ' . $user->getBlockedReason());
        }

        if (!$this->passwordHasher->isPasswordValid($user, $password)) {
            throw new UnauthorizedHttpException('', 'Неверный email или пароль');
        }

        // Update last login info
        $user->updateLastLogin($ip);
        $this->userRepository->save($user, true);

        // Generate access token
        $accessToken = $this->jwtManager->create($user);

        // Generate refresh token (for now, same as access token - will implement proper refresh in later stages)
        $refreshToken = $this->generateRefreshToken($user);

        return [
            'accessToken' => $accessToken,
            'refreshToken' => $refreshToken,
            'user' => [
                'id' => $user->getId()->toRfc4122(),
                'email' => $user->getEmail(),
                'firstName' => $user->getFirstName(),
                'lastName' => $user->getLastName(),
                'company' => [
                    'id' => $user->getCompany()->getId()->toRfc4122(),
                    'name' => $user->getCompany()->getName(),
                    'type' => $user->getCompany()->getType(),
                ],
            ],
        ];
    }

    /**
     * Get user info from token
     */
    public function getUserInfo(User $user): array
    {
        // Get all permissions from user's roles
        $permissions = [];
        foreach ($user->getUserRoles() as $role) {
            foreach ($role->getPermissions() as $permission) {
                $permissions[] = $permission->getName();
            }
        }
        $permissions = array_unique($permissions);

        return [
            'id' => $user->getId()->toRfc4122(),
            'email' => $user->getEmail(),
            'firstName' => $user->getFirstName(),
            'lastName' => $user->getLastName(),
            'fullName' => $user->getFullName(),
            'phone' => $user->getPhone(),
            'roles' => $user->getRoles(),
            'permissions' => $permissions,
            'company' => [
                'id' => $user->getCompany()->getId()->toRfc4122(),
                'name' => $user->getCompany()->getName(),
                'type' => $user->getCompany()->getType(),
                'inn' => $user->getCompany()->getInn(),
            ],
            'lastLoginAt' => $user->getLastLoginAt()?->format('Y-m-d H:i:s'),
            'lastLoginIp' => $user->getLastLoginIp(),
        ];
    }

    /**
     * Generate refresh token
     * For Stage 2, we'll use a simple approach
     * In later stages (Stage 7), we'll implement proper refresh token storage
     */
    private function generateRefreshToken(User $user): string
    {
        // For now, just create another JWT with longer TTL
        // Will implement proper refresh token storage in Stage 7
        return $this->jwtManager->create($user);
    }
}
