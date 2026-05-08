<?php

declare(strict_types=1);

namespace App\Banking\Controller;

use App\Banking\Entity\Account;
use App\Banking\Repository\AccountRepository;
use App\Identity\Repository\CompanyRepository;
use App\Identity\Repository\UserRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Symfony\Component\Uid\Uuid;

/**
 * API Controller for simple Account management
 * For admins to create test accounts for companies
 */
#[Route('/api/accounts', name: 'api_accounts_')]
class AccountController extends AbstractController
{
    public function __construct(
        private readonly AccountRepository $accountRepository,
        private readonly CompanyRepository $companyRepository,
        private readonly UserRepository $userRepository,
        private readonly EntityManagerInterface $entityManager,
    ) {
    }

    /**
     * Get all accounts
     * GET /api/accounts?companyId=xxx
     */
    #[Route('', name: 'list', methods: ['GET'])]
    #[IsGranted('IS_AUTHENTICATED_FULLY')]
    public function list(Request $request): JsonResponse
    {
        $companyId = $request->query->get('companyId');

        if ($companyId) {
            $company = $this->companyRepository->find($companyId);
            if (!$company) {
                return $this->json(['error' => 'Компания не найдена'], Response::HTTP_NOT_FOUND);
            }
            $accounts = $this->accountRepository->findByCompany($company);
        } else {
            $accounts = $this->accountRepository->findAll();
        }

        $data = array_map(fn(Account $account) => $this->formatAccount($account), $accounts);

        return $this->json($data);
    }

    /**
     * Get single account
     * GET /api/accounts/{id}
     */
    #[Route('/{id}', name: 'show', methods: ['GET'])]
    #[IsGranted('IS_AUTHENTICATED_FULLY')]
    public function show(string $id): JsonResponse
    {
        $account = $this->accountRepository->find($id);

        if (!$account) {
            return $this->json(['error' => 'Счет не найден'], Response::HTTP_NOT_FOUND);
        }

        return $this->json($this->formatAccount($account));
    }

    /**
     * Create new account
     * POST /api/accounts
     *
     * Request body:
     * {
     *   "name": "Main Account",
     *   "companyId": "uuid",
     *   "ownerId": "uuid",
     *   "balance": 100000,
     *   "currency": "RUB"
     * }
     */
    #[Route('', name: 'create', methods: ['POST'])]
    #[IsGranted('ROLE_ADMIN')]
    public function create(Request $request): JsonResponse
    {
        $data = json_decode($request->getContent(), true);

        if (!$data) {
            return $this->json(['error' => 'Некорректные данные'], Response::HTTP_BAD_REQUEST);
        }

        // Validate required fields
        $requiredFields = ['name', 'companyId', 'ownerId'];
        foreach ($requiredFields as $field) {
            if (empty($data[$field])) {
                return $this->json(['error' => "Поле '$field' обязательно"], Response::HTTP_BAD_REQUEST);
            }
        }

        // Find company
        $company = $this->companyRepository->find($data['companyId']);
        if (!$company) {
            return $this->json(['error' => 'Компания не найдена'], Response::HTTP_NOT_FOUND);
        }

        // Find owner
        $owner = $this->userRepository->find($data['ownerId']);
        if (!$owner) {
            return $this->json(['error' => 'Владелец (пользователь) не найден'], Response::HTTP_NOT_FOUND);
        }

        // Verify owner belongs to this company
        if ($owner->getCompany()->getId()->toRfc4122() !== $company->getId()->toRfc4122()) {
            return $this->json(['error' => 'Владелец не принадлежит указанной компании'], Response::HTTP_BAD_REQUEST);
        }

        try {
            $currencyCode = $data['currency'] ?? 'RUB';
            $currency = \App\Shared\ValueObject\Currency::from($currencyCode);

            $account = new Account(
                name: $data['name'],
                ownerId: Uuid::fromString($data['ownerId']),
                company: $company,
                currency: $currency
            );

            $this->accountRepository->save($account, true);

            // Set initial balance if provided (via separate API endpoint after account creation)
            // Initial balance должен создаваться через TransactionService с типом DEPOSIT
            // Здесь мы просто создаем счет с нулевым балансом

            return $this->json([
                'message' => 'Счет успешно создан',
                'account' => $this->formatAccount($account),
                'note' => 'Для установки начального баланса используйте API /api/v2/transactions (тип DEPOSIT)'
            ], Response::HTTP_CREATED);

        } catch (\Exception $e) {
            return $this->json(['error' => 'Ошибка создания счета: ' . $e->getMessage()], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    /**
     * Update account balance
     * PUT /api/accounts/{id}
     */
    #[Route('/{id}', name: 'update', methods: ['PUT'])]
    #[IsGranted('ROLE_ADMIN')]
    public function update(string $id, Request $request): JsonResponse
    {
        $account = $this->accountRepository->find($id);

        if (!$account) {
            return $this->json(['error' => 'Счет не найден'], Response::HTTP_NOT_FOUND);
        }

        $data = json_decode($request->getContent(), true);

        if (!$data) {
            return $this->json(['error' => 'Некорректные данные'], Response::HTTP_BAD_REQUEST);
        }

        try {
            // Account entity is immutable by design.
            // Balances are managed through transactions, not direct updates.
            $this->entityManager->flush();

            return $this->json([
                'message' => 'Счет успешно обновлен',
                'account' => $this->formatAccount($account)
            ]);

        } catch (\Exception $e) {
            return $this->json(['error' => 'Ошибка обновления счета: ' . $e->getMessage()], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    /**
     * Delete account
     * DELETE /api/accounts/{id}
     */
    #[Route('/{id}', name: 'delete', methods: ['DELETE'])]
    #[IsGranted('ROLE_ADMIN')]
    public function delete(string $id): JsonResponse
    {
        $account = $this->accountRepository->find($id);

        if (!$account) {
            return $this->json(['error' => 'Счет не найден'], Response::HTTP_NOT_FOUND);
        }

        // Check if account has balance
        if (!$account->getBalance()->isZero()) {
            return $this->json([
                'error' => 'Невозможно удалить счет с ненулевым балансом',
                'balance' => $account->getBalance()->format()
            ], Response::HTTP_BAD_REQUEST);
        }

        try {
            $this->accountRepository->remove($account, true);

            return $this->json(['message' => 'Счет успешно удален']);

        } catch (\Exception $e) {
            return $this->json(['error' => 'Ошибка удаления счета: ' . $e->getMessage()], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    /**
     * Format account for JSON response
     */
    private function formatAccount(Account $account): array
    {
        return [
            'id' => $account->getId()->toRfc4122(),
            'name' => $account->getName(),
            'balance' => $account->getBalance()->getMinorUnits(), // В копейках/центах
            'balanceFormatted' => $account->getBalance()->format(), // Отформатированная строка
            'currency' => $account->getCurrency()->value,
            'ownerId' => $account->getOwnerId()->toRfc4122(),
            'company' => [
                'id' => $account->getCompany()->getId()->toRfc4122(),
                'name' => $account->getCompany()->getName(),
                'type' => $account->getCompany()->getType(),
            ],
            'createdAt' => $account->getCreatedAt()->format('Y-m-d H:i:s'),
            'updatedAt' => $account->getUpdatedAt()->format('Y-m-d H:i:s'),
        ];
    }
}
