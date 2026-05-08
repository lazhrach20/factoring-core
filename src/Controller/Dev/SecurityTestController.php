<?php

declare(strict_types=1);

namespace App\Controller\Dev;

use App\Identity\Entity\User;
use App\Identity\Service\SecurityVersionService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Http\Attribute\CurrentUser;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/api/test')]
#[IsGranted('IS_AUTHENTICATED')]
class SecurityTestController extends AbstractController
{
    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly UserPasswordHasherInterface $passwordHasher,
        private readonly SecurityVersionService $securityVersionService
    ) {
    }

    /**
     * Test endpoint: Change password (will invalidate current token)
     * POST /api/test/change-password
     * Body: {"newPassword": "newpass123"}
     */
    #[Route('/change-password', name: 'api_test_change_password', methods: ['POST'])]
    public function changePassword(
        Request $request,
        #[CurrentUser] User $user
    ): JsonResponse {
        $data = json_decode($request->getContent(), true);
        $newPassword = $data['newPassword'] ?? null;

        if (!$newPassword) {
            return $this->json(['error' => 'newPassword is required'], 400);
        }

        $oldSecurityVersion = $user->getSecurityVersion();

        // Hash and set new password
        $hashedPassword = $this->passwordHasher->hashPassword($user, $newPassword);
        $user->setPassword($hashedPassword);

        // Increment security version (invalidates all existing tokens)
        $this->securityVersionService->incrementSecurityVersion($user);

        $newSecurityVersion = $user->getSecurityVersion();

        return $this->json([
            'message' => 'Password changed successfully. All existing tokens are now invalid.',
            'oldSecurityVersion' => $oldSecurityVersion,
            'newSecurityVersion' => $newSecurityVersion,
            'note' => 'Your current token will stop working. Please login again.',
        ]);
    }

    /**
     * Test endpoint: Get current security version
     * GET /api/test/security-version
     */
    #[Route('/security-version', name: 'api_test_security_version', methods: ['GET'])]
    public function getSecurityVersion(#[CurrentUser] User $user): JsonResponse
    {
        return $this->json([
            'userId' => $user->getId()->toRfc4122(),
            'email' => $user->getEmail(),
            'securityVersion' => $user->getSecurityVersion(),
        ]);
    }
}
