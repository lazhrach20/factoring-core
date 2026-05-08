<?php

declare(strict_types=1);

namespace App\Identity\Controller;

use App\Identity\Dto\AssignRoleRequest;
use App\Identity\Repository\RoleRepository;
use App\Identity\Repository\UserRepository;
use App\Identity\Service\RoleService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\MapRequestPayload;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/api/admin/roles')]
#[IsGranted('ROLE_ADMIN')]
class RoleController extends AbstractController
{
    public function __construct(
        private readonly RoleService $roleService,
        private readonly RoleRepository $roleRepository,
        private readonly UserRepository $userRepository,
        private readonly EntityManagerInterface $entityManager
    ) {
    }

    /**
     * Get all roles
     */
    #[Route('', name: 'api_roles_list', methods: ['GET'])]
    public function list(): JsonResponse
    {
        $roles = $this->roleRepository->findAllOrderedByName();

        $data = array_map(function ($role) {
            return [
                'id' => $role->getId()->toRfc4122(),
                'name' => $role->getName(),
                'description' => $role->getDescription(),
                'permissions' => array_map(function ($permission) {
                    return [
                        'id' => $permission->getId()->toRfc4122(),
                        'name' => $permission->getName(),
                        'description' => $permission->getDescription(),
                        'category' => $permission->getCategory(),
                    ];
                }, $role->getPermissions()->toArray()),
                'usersCount' => $role->getUsers()->count(),
            ];
        }, $roles);

        return $this->json($data);
    }

    /**
     * Get single role by ID
     */
    #[Route('/{id}', name: 'api_roles_show', methods: ['GET'])]
    public function show(string $id): JsonResponse
    {
        try {
            $role = $this->roleRepository->findById(\Symfony\Component\Uid\Uuid::fromString($id));

            if (!$role) {
                return $this->json(['error' => 'Роль не найдена'], Response::HTTP_NOT_FOUND);
            }

            return $this->json([
                'id' => $role->getId()->toRfc4122(),
                'name' => $role->getName(),
                'description' => $role->getDescription(),
                'permissions' => array_map(function ($permission) {
                    return [
                        'id' => $permission->getId()->toRfc4122(),
                        'name' => $permission->getName(),
                        'description' => $permission->getDescription(),
                        'category' => $permission->getCategory(),
                    ];
                }, $role->getPermissions()->toArray()),
                'users' => array_map(function ($user) {
                    return [
                        'id' => $user->getId()->toRfc4122(),
                        'email' => $user->getEmail(),
                        'fullName' => $user->getFullName(),
                    ];
                }, $role->getUsers()->toArray()),
            ]);
        } catch (\Exception $e) {
            return $this->json(['error' => 'Некорректный ID роли'], Response::HTTP_BAD_REQUEST);
        }
    }

    /**
     * Assign role to user
     */
    #[Route('/assign', name: 'api_roles_assign', methods: ['POST'])]
    public function assignRole(
        #[MapRequestPayload] AssignRoleRequest $request
    ): JsonResponse {
        $user = $this->userRepository->findByEmail($request->email);
        if (!$user) {
            return $this->json(
                ['error' => 'Пользователь не найден'],
                Response::HTTP_NOT_FOUND
            );
        }

        $role = $this->roleRepository->findByName($request->roleName);
        if (!$role) {
            return $this->json(
                ['error' => 'Роль не найдена'],
                Response::HTTP_NOT_FOUND
            );
        }

        if ($user->hasRole($request->roleName)) {
            return $this->json(
                ['error' => 'У пользователя уже есть эта роль'],
                Response::HTTP_BAD_REQUEST
            );
        }

        $this->roleService->assignRoleToUser($user, $role->getId());
        $this->entityManager->flush();

        return $this->json([
            'message' => 'Роль успешно назначена',
            'user' => [
                'id' => $user->getId()->toRfc4122(),
                'email' => $user->getEmail(),
                'roles' => $user->getRoles(),
            ]
        ]);
    }

    /**
     * Remove role from user
     */
    #[Route('/remove', name: 'api_roles_remove', methods: ['POST'])]
    public function removeRole(
        #[MapRequestPayload] AssignRoleRequest $request
    ): JsonResponse {
        $user = $this->userRepository->findByEmail($request->email);
        if (!$user) {
            return $this->json(
                ['error' => 'Пользователь не найден'],
                Response::HTTP_NOT_FOUND
            );
        }

        $role = $this->roleRepository->findByName($request->roleName);
        if (!$role) {
            return $this->json(
                ['error' => 'Роль не найдена'],
                Response::HTTP_NOT_FOUND
            );
        }

        if (!$user->hasRole($request->roleName)) {
            return $this->json(
                ['error' => 'У пользователя нет этой роли'],
                Response::HTTP_BAD_REQUEST
            );
        }

        $this->roleService->removeRoleFromUser($user, $role->getId());
        $this->entityManager->flush();

        return $this->json([
            'message' => 'Роль успешно удалена',
            'user' => [
                'id' => $user->getId()->toRfc4122(),
                'email' => $user->getEmail(),
                'roles' => $user->getRoles(),
            ]
        ]);
    }
}
