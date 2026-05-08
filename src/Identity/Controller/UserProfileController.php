<?php

declare(strict_types=1);

namespace App\Identity\Controller;

use App\Banking\Repository\AccountRepository;
use App\Banking\Repository\TransactionRepository;
use App\Identity\Entity\User;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Http\Attribute\CurrentUser;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/api/users/me')]
#[IsGranted('IS_AUTHENTICATED')]
class UserProfileController extends AbstractController
{
    public function __construct(
        private readonly AccountRepository $accountRepository,
        private readonly TransactionRepository $transactionRepository
    ) {
    }

    /**
     * Получить информацию о текущем пользователе
     * GET /api/users/me
     */
    #[Route('', name: 'api_user_me', methods: ['GET'])]
    public function getCurrentUser(#[CurrentUser] User $user): JsonResponse
    {
        $company = $user->getCompany();

        return $this->json([
            'id' => $user->getId()->toRfc4122(),
            'email' => $user->getEmail(),
            'firstName' => $user->getFirstName(),
            'lastName' => $user->getLastName(),
            'phone' => $user->getPhone(),
            'isBlocked' => $user->isBlocked(),
            'createdAt' => $user->getCreatedAt()->format('Y-m-d H:i:s'),
            'lastLoginAt' => $user->getLastLoginAt()?->format('Y-m-d H:i:s'),
            'company' => [
                'id' => $company->getId()->toRfc4122(),
                'name' => $company->getName(),
                'inn' => $company->getInn(),
                'type' => $company->getType(),
                'status' => $company->getStatus(),
            ],
            'roles' => array_values(array_map(
                fn($role) => $role->getName(),
                $user->getUserRoles()->toArray()
            )),
        ]);
    }

    /**
     * Получить банковские счета компании текущего пользователя (простые Account для тестирования)
     * GET /api/users/me/accounts
     */
    #[Route('/accounts', name: 'api_user_me_accounts', methods: ['GET'])]
    public function getAccounts(#[CurrentUser] User $user): JsonResponse
    {
        $company = $user->getCompany();

        // Получаем простые тестовые счета (Account)
        $accounts = $this->accountRepository->findByCompany($company);

        $data = array_map(fn($account) => [
            'id' => $account->getId()->toRfc4122(),
            'name' => $account->getName(),
            'balance' => $account->getBalance()->getMinorUnits(), // В копейках/центах
            'balanceFormatted' => $account->getBalance()->format(), // Отформатированная строка
            'currency' => $account->getCurrency()->value,
            'company' => [
                'id' => $account->getCompany()->getId()->toRfc4122(),
                'name' => $account->getCompany()->getName(),
                'type' => $account->getCompany()->getType(),
            ],
            'createdAt' => $account->getCreatedAt()->format('Y-m-d H:i:s'),
        ], $accounts);

        return $this->json($data);
    }

    /**
     * Получить транзакции компании текущего пользователя с пагинацией
     * GET /api/users/me/transactions?limit=10&offset=0
     */
    #[Route('/transactions', name: 'api_user_me_transactions', methods: ['GET'])]
    public function getTransactions(
        #[CurrentUser] User $user,
        Request $request
    ): JsonResponse {
        $company = $user->getCompany();

        $limit = min((int) $request->query->get('limit', 10), 100); // макс 100
        $offset = (int) $request->query->get('offset', 0);

        // Получить все счета компании
        $accounts = $this->accountRepository->findBy(['company' => $company]);

        if (empty($accounts)) {
            return $this->json([
                'transactions' => [],
                'pagination' => [
                    'limit' => $limit,
                    'offset' => $offset,
                    'total' => 0,
                    'hasMore' => false,
                ],
            ]);
        }

        // Собрать все транзакции из всех счетов (используем новую систему)
        $allTransactions = [];
        $transactionIds = []; // Для отслеживания дубликатов

        foreach ($accounts as $account) {
            // Берем больше транзакций с каждого счета, чтобы после фильтрации было достаточно
            $transactions = $this->transactionRepository->findByAccount($account, $limit + $offset + 50);
            foreach ($transactions as $transaction) {
                $txId = $transaction->getId()->toRfc4122();
                if (!isset($transactionIds[$txId])) {
                    $transactionIds[$txId] = true;
                    $allTransactions[] = $transaction;
                }
            }
        }

        // Сортировка по дате (новые сначала)
        usort($allTransactions, fn($a, $b) => $b->getExecutedAt() <=> $a->getExecutedAt());

        // Применяем offset и limit
        $total = count($allTransactions);
        $paginatedTransactions = array_slice($allTransactions, $offset, $limit);

        // Форматируем для ответа (новая структура)
        $data = array_map(fn($transaction) => [
            'id' => $transaction->getId()->toRfc4122(),
            'debitAccount' => [
                'id' => $transaction->getDebitAccount()->getId()->toRfc4122(),
                'name' => $transaction->getDebitAccount()->getName(),
                'company' => $transaction->getDebitAccount()->getCompany()->getName(),
            ],
            'creditAccount' => [
                'id' => $transaction->getCreditAccount()->getId()->toRfc4122(),
                'name' => $transaction->getCreditAccount()->getName(),
                'company' => $transaction->getCreditAccount()->getCompany()->getName(),
            ],
            'amount' => [
                'value' => $transaction->getAmount()->toFloat(),
                'formatted' => $transaction->getAmount()->format(),
                'currency' => $transaction->getAmount()->getCurrency()->value,
            ],
            'type' => $transaction->getType()->value,
            'typeLabel' => $transaction->getType()->getLabel(),
            'status' => $transaction->getStatus()->value,
            'statusLabel' => $transaction->getStatus()->getLabel(),
            'description' => $transaction->getDescription(),
            'initiatedBy' => [
                'id' => $transaction->getInitiatedBy()->getId()->toRfc4122(),
                'email' => $transaction->getInitiatedBy()->getEmail(),
            ],
            'executedAt' => $transaction->getExecutedAt()->format('Y-m-d H:i:s'),
            'createdAt' => $transaction->getCreatedAt()->format('Y-m-d H:i:s'),
        ], $paginatedTransactions);

        return $this->json([
            'transactions' => $data,
            'pagination' => [
                'limit' => $limit,
                'offset' => $offset,
                'total' => $total,
                'hasMore' => ($offset + $limit) < $total,
            ],
        ]);
    }
}
