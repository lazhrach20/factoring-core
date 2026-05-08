<?php

declare(strict_types=1);

namespace App\Identity\Service;

use App\Identity\Entity\User;
use App\Identity\Repository\UserRepository;

/**
 * Service for managing security versions
 * Used to invalidate all user tokens when password changes or user is blocked
 */
class SecurityVersionService
{
    public function __construct(
        private readonly UserRepository $userRepository
    ) {
    }

    /**
     * Increment user's security version
     * This will invalidate all existing JWT tokens for this user
     */
    public function incrementSecurityVersion(User $user): void
    {
        $user->incrementSecurityVersion();
        $this->userRepository->save($user, true);
    }

    /**
     * Check if user's security version is valid
     */
    public function isSecurityVersionValid(User $user, int $tokenSecurityVersion): bool
    {
        return $user->getSecurityVersion() === $tokenSecurityVersion;
    }
}
