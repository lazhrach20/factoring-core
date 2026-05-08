<?php

declare(strict_types=1);

namespace App\Identity\Controller;

use App\Identity\Repository\UserRepository;
use App\Identity\Repository\CompanyRepository;
use App\Identity\Service\SecurityVersionService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/api/admin/users')]
class UserManagementController extends AbstractController
{
    public function __construct(
        private readonly UserRepository $userRepository,
        private readonly CompanyRepository $companyRepository,
        private readonly EntityManagerInterface $entityManager,
        private readonly SecurityVersionService $securityVersionService
    ) {
    }

    /**
     * Get all users (requires VIEW_USERS permission or ROLE_ADMIN)
     * GET /api/admin/users?companyId=xxx
     * Факторы видят всех пользователей, остальные только своей компании
     */
    #[Route('', name: 'api_users_list', methods: ['GET'])]
    #[IsGranted('ROLE_ADMIN')]
    public function list(Request $request): JsonResponse
    {
        $currentUser = $this->getUser();
        $currentCompany = $currentUser->getCompany();
        $companyId = $request->query->get('companyId');

        // Проверяем является ли текущая компания фактором
        $isFactor = $currentCompany->getType() === 'factor';

        if ($companyId) {
            // Если указан companyId, проверяем права доступа
            $company = $this->companyRepository->find($companyId);
            if (!$company) {
                return $this->json(['error' => 'Компания не найдена'], Response::HTTP_NOT_FOUND);
            }

            // Не-факторы могут видеть только пользователей своей компании
            if (!$isFactor && $company->getId()->toRfc4122() !== $currentCompany->getId()->toRfc4122()) {
                return $this->json(['error' => 'Доступ запрещен'], Response::HTTP_FORBIDDEN);
            }

            $users = $this->userRepository->findBy(['company' => $company]);
        } else {
            // Если companyId не указан
            if ($isFactor) {
                // Фактор видит всех пользователей
                $users = $this->userRepository->findAll();
            } else {
                // Остальные видят только пользователей своей компании
                $users = $this->userRepository->findBy(['company' => $currentCompany]);
            }
        }

        $data = array_map(function ($user) {
            return [
                'id' => $user->getId()->toRfc4122(),
                'email' => $user->getEmail(),
                'firstName' => $user->getFirstName(),
                'lastName' => $user->getLastName(),
                'fullName' => $user->getFullName(),
                'phone' => $user->getPhone(),
                'company' => [
                    'id' => $user->getCompany()->getId()->toRfc4122(),
                    'name' => $user->getCompany()->getName(),
                    'type' => $user->getCompany()->getType(),
                ],
                'roles' => $user->getRoles(),
                'isBlocked' => $user->isBlocked(),
                'blockedReason' => $user->getBlockedReason(),
                'createdAt' => $user->getCreatedAt()->format('Y-m-d H:i:s'),
                'lastLoginAt' => $user->getLastLoginAt()?->format('Y-m-d H:i:s'),
            ];
        }, $users);

        return $this->json([
            'users' => $data,
            'total' => count($data),
        ]);
    }

    /**
     * Get user by ID
     */
    #[Route('/{id}', name: 'api_users_show', methods: ['GET'])]
    #[IsGranted('ROLE_ADMIN')]
    public function show(string $id): JsonResponse
    {
        try {
            $user = $this->userRepository->findById(\Symfony\Component\Uid\Uuid::fromString($id));

            if (!$user) {
                return $this->json(['error' => 'Пользователь не найден'], Response::HTTP_NOT_FOUND);
            }

            return $this->json([
                'id' => $user->getId()->toRfc4122(),
                'email' => $user->getEmail(),
                'firstName' => $user->getFirstName(),
                'lastName' => $user->getLastName(),
                'fullName' => $user->getFullName(),
                'phone' => $user->getPhone(),
                'company' => [
                    'id' => $user->getCompany()->getId()->toRfc4122(),
                    'name' => $user->getCompany()->getName(),
                    'type' => $user->getCompany()->getType(),
                    'inn' => $user->getCompany()->getInn(),
                ],
                'roles' => array_map(
                    fn($role) => [
                        'id' => $role->getId()->toRfc4122(),
                        'name' => $role->getName(),
                        'description' => $role->getDescription(),
                    ],
                    $user->getUserRoles()->toArray()
                ),
                'isBlocked' => $user->isBlocked(),
                'blockedReason' => $user->getBlockedReason(),
                'createdAt' => $user->getCreatedAt()->format('Y-m-d H:i:s'),
                'updatedAt' => $user->getUpdatedAt()->format('Y-m-d H:i:s'),
                'lastLoginAt' => $user->getLastLoginAt()?->format('Y-m-d H:i:s'),
                'lastLoginIp' => $user->getLastLoginIp(),
            ]);
        } catch (\Exception $e) {
            return $this->json(['error' => 'Некорректный ID пользователя'], Response::HTTP_BAD_REQUEST);
        }
    }

    /**
     * Block user
     */
    #[Route('/{id}/block', name: 'api_users_block', methods: ['POST'])]
    #[IsGranted('ROLE_ADMIN')]
    public function block(string $id): JsonResponse
    {
        try {
            $user = $this->userRepository->findById(\Symfony\Component\Uid\Uuid::fromString($id));

            if (!$user) {
                return $this->json(['error' => 'Пользователь не найден'], Response::HTTP_NOT_FOUND);
            }

            if ($user->isBlocked()) {
                return $this->json(['error' => 'Пользователь уже заблокирован'], Response::HTTP_BAD_REQUEST);
            }

            // Prevent blocking yourself
            if ($this->getUser() && $this->getUser()->getUserIdentifier() === $user->getEmail()) {
                return $this->json(['error' => 'Нельзя заблокировать себя'], Response::HTTP_BAD_REQUEST);
            }

            $user->block('Заблокирован администратором');

            // Increment security version to invalidate all existing tokens
            $this->securityVersionService->incrementSecurityVersion($user);

            $this->entityManager->flush();

            return $this->json([
                'message' => 'Пользователь заблокирован',
                'user' => [
                    'id' => $user->getId()->toRfc4122(),
                    'email' => $user->getEmail(),
                    'isBlocked' => $user->isBlocked(),
                    'blockedReason' => $user->getBlockedReason(),
                ]
            ]);
        } catch (\Exception $e) {
            return $this->json(['error' => 'Ошибка при блокировке пользователя'], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    /**
     * Unblock user
     */
    #[Route('/{id}/unblock', name: 'api_users_unblock', methods: ['POST'])]
    #[IsGranted('ROLE_ADMIN')]
    public function unblock(string $id): JsonResponse
    {
        try {
            $user = $this->userRepository->findById(\Symfony\Component\Uid\Uuid::fromString($id));

            if (!$user) {
                return $this->json(['error' => 'Пользователь не найден'], Response::HTTP_NOT_FOUND);
            }

            if (!$user->isBlocked()) {
                return $this->json(['error' => 'Пользователь не заблокирован'], Response::HTTP_BAD_REQUEST);
            }

            $user->unblock();
            $this->entityManager->flush();

            return $this->json([
                'message' => 'Пользователь разблокирован',
                'user' => [
                    'id' => $user->getId()->toRfc4122(),
                    'email' => $user->getEmail(),
                    'isBlocked' => $user->isBlocked(),
                ]
            ]);
        } catch (\Exception $e) {
            return $this->json(['error' => 'Ошибка при разблокировке пользователя'], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }
}
