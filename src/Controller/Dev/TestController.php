<?php

declare(strict_types=1);

namespace App\Controller\Dev;

use App\Identity\Entity\User;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Http\Attribute\CurrentUser;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/api/test')]
#[IsGranted('IS_AUTHENTICATED')]
class TestController extends AbstractController
{
    /**
     * Тестовый endpoint для проверки прав доступа
     * GET /api/test/permissions
     */
    #[Route('/permissions', name: 'api_test_permissions', methods: ['GET'])]
    public function testPermissions(#[CurrentUser] User $user): JsonResponse
    {
        $permissions = [];
        foreach ($user->getUserRoles() as $role) {
            foreach ($role->getPermissions() as $permission) {
                $permissions[] = $permission->getName();
            }
        }

        return $this->json([
            'user' => [
                'id' => $user->getId()->toRfc4122(),
                'email' => $user->getEmail(),
                'fullName' => $user->getFullName(),
            ],
            'roles' => $user->getRoles(),
            'permissions' => array_unique($permissions),
            'company' => [
                'id' => $user->getCompany()->getId()->toRfc4122(),
                'name' => $user->getCompany()->getName(),
                'type' => $user->getCompany()->getType(),
            ],
            'checks' => [
                'hasCreateUser' => $user->hasPermission('CREATE_USER'),
                'hasEditUser' => $user->hasPermission('EDIT_USER'),
                'hasCreateTransaction' => $user->hasPermission('CREATE_TRANSACTION'),
                'hasApproveTransaction' => $user->hasPermission('APPROVE_TRANSACTION'),
                'hasViewReports' => $user->hasPermission('VIEW_REPORTS'),
                'isAdmin' => $user->hasRole('ROLE_ADMIN'),
                'isAccountant' => $user->hasRole('ROLE_ACCOUNTANT'),
            ],
        ]);
    }

    /**
     * Тест voter'а для permissions
     */
    #[Route('/voter/permission/{permission}', name: 'api_test_voter_permission', methods: ['GET'])]
    public function testPermissionVoter(string $permission): JsonResponse
    {
        $isGranted = $this->isGranted($permission);

        return $this->json([
            'permission' => $permission,
            'granted' => $isGranted,
            'message' => $isGranted
                ? "У вас есть право: {$permission}"
                : "У вас нет права: {$permission}",
        ]);
    }
}
