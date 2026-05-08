<?php

declare(strict_types=1);

namespace App\Banking\Controller\Api;

use App\Banking\Entity\Account;
use App\Banking\Entity\TransactionType;
use App\Banking\Message\ProcessDepositMessage;
use App\Banking\Repository\AccountRepository;
use App\Banking\Repository\TransactionRepository;
use App\Banking\Service\TransactionService;
use App\Identity\Repository\CompanyRepository;
use App\Shared\Exception\InsufficientFundsException;
use App\Shared\ValueObject\Money;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Symfony\Component\Uid\Uuid;

/**
 * API для операций с транзакциями (Immutable Ledger)
 */
#[Route('/api/v2/transactions')]
#[IsGranted('IS_AUTHENTICATED')]
class TransactionApiController extends AbstractController
{
    public function __construct(
        private readonly TransactionService $transactionService,
        private readonly TransactionRepository $transactionRepository,
        private readonly AccountRepository $accountRepository,
        private readonly CompanyRepository $companyRepository,
        private readonly EntityManagerInterface $entityManager,
        private readonly MessageBusInterface $messageBus,
    ) {
    }

    /**
     * POST /api/v2/transactions/transfer
     *
     * Выполнить перевод между счетами
     *
     * Body:
     * {
     *   "fromAccountId": "uuid",
     *   "toAccountId": "uuid",
     *   "amount": 1000.50,
     *   "currency": "RUB",
     *   "type": "transfer",
     *   "idempotencyKey": "uuid",  // Опционально, будет сгенерирован
     *   "description": "Описание операции",
     *   "metadata": {}
     * }
     */
    #[Route('/transfer', methods: ['POST'])]
    public function transfer(Request $request): JsonResponse
    {
        try {
            $data = json_decode($request->getContent(), true);

            // Валидация входных данных
            if (!isset($data['fromAccountId'], $data['toAccountId'], $data['amount'])) {
                return $this->json([
                    'error' => 'Missing required fields: fromAccountId, toAccountId, amount'
                ], Response::HTTP_BAD_REQUEST);
            }

            // Получение счетов
            $fromAccount = $this->accountRepository->find(Uuid::fromString($data['fromAccountId']));
            $toAccount = $this->accountRepository->find(Uuid::fromString($data['toAccountId']));

            if (!$fromAccount) {
                return $this->json(['error' => 'Source account not found'], Response::HTTP_NOT_FOUND);
            }

            if (!$toAccount) {
                return $this->json(['error' => 'Destination account not found'], Response::HTTP_NOT_FOUND);
            }

            // Проверка доступа (пользователь должен иметь доступ к fromAccount)
            /** @var \App\Identity\Entity\User $user */
            $user = $this->getUser();

            if ($fromAccount->getCompany()->getId()->toString() !== $user->getCompany()->getId()->toString()) {
                return $this->json(['error' => 'Access denied to source account'], Response::HTTP_FORBIDDEN);
            }

            // Создание Money объекта
            $currency = $fromAccount->getCurrency();
            $amount = Money::fromFloat((float) $data['amount'], $currency);

            // Idempotency key
            $idempotencyKey = $data['idempotencyKey'] ?? Uuid::v4()->toRfc4122();

            // Тип транзакции
            $type = isset($data['type'])
                ? TransactionType::from($data['type'])
                : TransactionType::TRANSFER;

            // Выполнение перевода
            $transaction = $this->transactionService->transfer(
                from: $fromAccount,
                to: $toAccount,
                amount: $amount,
                type: $type,
                idempotencyKey: $idempotencyKey,
                initiatedBy: $user,
                description: $data['description'] ?? null,
                metadata: $data['metadata'] ?? null,
                relatedEntityId: isset($data['relatedEntityId']) ? Uuid::fromString($data['relatedEntityId']) : null,
                relatedEntityType: $data['relatedEntityType'] ?? null
            );

            return $this->json([
                'success' => true,
                'transaction' => $this->formatTransaction($transaction),
                'message' => 'Transfer completed successfully'
            ], Response::HTTP_CREATED);

        } catch (InsufficientFundsException $e) {
            return $this->json([
                'error' => 'Insufficient funds',
                'details' => [
                    'required' => $e->getRequired()->format(),
                    'available' => $e->getAvailable()->format(),
                ]
            ], Response::HTTP_UNPROCESSABLE_ENTITY);

        } catch (\InvalidArgumentException $e) {
            return $this->json([
                'error' => 'Validation error',
                'message' => $e->getMessage()
            ], Response::HTTP_BAD_REQUEST);

        } catch (\Exception $e) {
            return $this->json([
                'error' => 'Internal server error',
                'message' => $e->getMessage()
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    /**
     * POST /api/v2/transactions/deposit
     *
     * Пополнить счет (создать транзакцию DEPOSIT)
     * Для тестирования - пополнение из "внешнего источника"
     *
     * Body:
     * {
     *   "accountId": "uuid",
     *   "amount": 1000.50,
     *   "description": "Пополнение счета"
     * }
     */
    #[Route('/deposit', methods: ['POST'])]
    public function deposit(Request $request): JsonResponse
    {
        try {
            $data = json_decode($request->getContent(), true);

            if (!isset($data['accountId'], $data['amount'])) {
                return $this->json([
                    'error' => 'Missing required fields: accountId, amount'
                ], Response::HTTP_BAD_REQUEST);
            }

            $account = $this->accountRepository->find(Uuid::fromString($data['accountId']));
            if (!$account) {
                return $this->json(['error' => 'Account not found'], Response::HTTP_NOT_FOUND);
            }

            /** @var \App\Identity\Entity\User $user */
            $user = $this->getUser();

            // Проверка доступа - пользователь должен иметь доступ к своим счетам
            if ($account->getCompany()->getId()->toString() !== $user->getCompany()->getId()->toString()) {
                return $this->json(['error' => 'Access denied to account'], Response::HTTP_FORBIDDEN);
            }

            $currency = $account->getCurrency();
            $amount = Money::fromFloat((float) $data['amount'], $currency);
            $idempotencyKey = $data['idempotencyKey'] ?? Uuid::v4()->toRfc4122();

            // Проверяем существующую транзакцию (идемпотентность)
            $existingTransaction = $this->transactionRepository->findByIdempotencyKey($idempotencyKey);
            if ($existingTransaction !== null) {
                return $this->json([
                    'success' => true,
                    'transaction' => $this->formatTransaction($existingTransaction),
                    'message' => 'Deposit already processed (idempotency)'
                ], Response::HTTP_OK);
            }

            // Получаем или создаем системный счет для внешних операций ДО начала транзакции
            $systemAccount = $this->getOrCreateSystemAccount($account->getCurrency());

            // Создаем транзакцию депозита со статусом PENDING
            $this->entityManager->beginTransaction();

            try {
                $transaction = \App\Banking\Entity\Transaction::create(
                    id: Uuid::v4(),
                    debitAccount: $systemAccount, // Списываем с системного счета
                    creditAccount: $account,      // Зачисляем на счет пользователя
                    amount: $amount,
                    type: TransactionType::DEPOSIT,
                    idempotencyKey: $idempotencyKey,
                    initiatedBy: $user,
                    description: $data['description'] ?? 'Пополнение счета',
                    metadata: ['source' => 'external_test', 'test' => true],
                    relatedEntityId: null,
                    relatedEntityType: null
                );

                // НЕ вызываем markAsCompleted() - транзакция остается в PENDING
                // НЕ изменяем балансы сейчас - это сделает worker после успеха

                $this->entityManager->persist($transaction);
                $this->entityManager->flush();
                $this->entityManager->commit();

                // Отправляем в очередь для асинхронной обработки
                $this->messageBus->dispatch(
                    new ProcessDepositMessage(
                        transactionId: $transaction->getId()->toRfc4122(),
                        idempotencyKey: $idempotencyKey
                    )
                );

                return $this->json([
                    'success' => true,
                    'transaction' => $this->formatTransaction($transaction),
                    'message' => 'Deposit request accepted for processing',
                    'status' => 'pending'
                ], Response::HTTP_ACCEPTED);

            } catch (\Exception $e) {
                $this->entityManager->rollback();
                throw $e;
            }

        } catch (\Exception $e) {
            return $this->json([
                'error' => 'Internal server error',
                'message' => $e->getMessage()
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    /**
     * GET /api/v2/transactions/{id}
     *
     * Получить детали транзакции
     */
    #[Route('/{id}', methods: ['GET'])]
    public function show(string $id): JsonResponse
    {
        try {
            $transaction = $this->transactionRepository->find(Uuid::fromString($id));

            if (!$transaction) {
                return $this->json(['error' => 'Transaction not found'], Response::HTTP_NOT_FOUND);
            }

            // Проверка доступа
            /** @var \App\Identity\Entity\User $user */
            $user = $this->getUser();
            $userCompanyId = $user->getCompany()->getId()->toString();

            $debitCompanyId = $transaction->getDebitAccount()->getCompany()->getId()->toString();
            $creditCompanyId = $transaction->getCreditAccount()->getCompany()->getId()->toString();

            if ($debitCompanyId !== $userCompanyId && $creditCompanyId !== $userCompanyId) {
                return $this->json(['error' => 'Access denied'], Response::HTTP_FORBIDDEN);
            }

            return $this->json([
                'transaction' => $this->formatTransaction($transaction)
            ]);

        } catch (\Exception $e) {
            return $this->json([
                'error' => 'Invalid transaction ID'
            ], Response::HTTP_BAD_REQUEST);
        }
    }

    /**
     * GET /api/v2/transactions
     *
     * Список транзакций текущей компании
     */
    #[Route('', methods: ['GET'])]
    public function list(Request $request): JsonResponse
    {
        /** @var \App\Identity\Entity\User $user */
        $user = $this->getUser();
        $company = $user->getCompany();

        // Получить все счета компании
        $accounts = $this->accountRepository->findBy(['company' => $company]);

        $allTransactions = [];
        $transactionIds = []; // Для отслеживания дубликатов

        foreach ($accounts as $account) {
            $transactions = $this->transactionRepository->findByAccount($account, 50);
            foreach ($transactions as $transaction) {
                $txId = $transaction->getId()->toRfc4122();
                if (!isset($transactionIds[$txId])) {
                    $transactionIds[$txId] = true;
                    $allTransactions[] = $transaction;
                }
            }
        }

        // Сортировка по дате
        usort($allTransactions, fn($a, $b) => $b->getExecutedAt() <=> $a->getExecutedAt());

        // Ограничение
        $limit = (int) $request->query->get('limit', 100);
        $allTransactions = array_slice($allTransactions, 0, $limit);

        return $this->json([
            'transactions' => array_map(
                fn($t) => $this->formatTransaction($t),
                $allTransactions
            ),
            'total' => count($allTransactions)
        ]);
    }

    /**
     * POST /api/v2/transactions/{id}/reverse
     *
     * Реверсировать транзакцию
     */
    #[Route('/{id}/reverse', methods: ['POST'])]
    public function reverse(string $id, Request $request): JsonResponse
    {
        try {
            $transaction = $this->transactionRepository->find(Uuid::fromString($id));

            if (!$transaction) {
                return $this->json(['error' => 'Transaction not found'], Response::HTTP_NOT_FOUND);
            }

            // Проверка доступа
            /** @var \App\Identity\Entity\User $user */
            $user = $this->getUser();
            $userCompanyId = $user->getCompany()->getId()->toString();
            $debitCompanyId = $transaction->getDebitAccount()->getCompany()->getId()->toString();

            if ($debitCompanyId !== $userCompanyId) {
                return $this->json(['error' => 'Only source account owner can reverse'], Response::HTTP_FORBIDDEN);
            }

            $data = json_decode($request->getContent(), true);
            $reason = $data['reason'] ?? 'Manual reversal';
            $idempotencyKey = $data['idempotencyKey'] ?? Uuid::v4()->toRfc4122();

            // Реверс
            $reversalTransaction = $this->transactionService->reverse(
                original: $transaction,
                initiatedBy: $user,
                reason: $reason,
                idempotencyKey: $idempotencyKey
            );

            return $this->json([
                'success' => true,
                'original' => $this->formatTransaction($transaction),
                'reversal' => $this->formatTransaction($reversalTransaction),
                'message' => 'Transaction reversed successfully'
            ]);

        } catch (\LogicException $e) {
            return $this->json([
                'error' => $e->getMessage()
            ], Response::HTTP_UNPROCESSABLE_ENTITY);

        } catch (\Exception $e) {
            return $this->json([
                'error' => 'Failed to reverse transaction',
                'message' => $e->getMessage()
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    /**
     * GET /api/v2/transactions/account/{accountId}/balance
     *
     * Получить баланс счета (real-time из транзакций)
     */
    #[Route('/account/{accountId}/balance', methods: ['GET'])]
    public function getAccountBalance(string $accountId): JsonResponse
    {
        try {
            $account = $this->accountRepository->find(Uuid::fromString($accountId));

            if (!$account) {
                return $this->json(['error' => 'Account not found'], Response::HTTP_NOT_FOUND);
            }

            // Проверка доступа
            /** @var \App\Identity\Entity\User $user */
            $user = $this->getUser();
            if ($account->getCompany()->getId()->toString() !== $user->getCompany()->getId()->toString()) {
                return $this->json(['error' => 'Access denied'], Response::HTTP_FORBIDDEN);
            }

            // Кэшированный баланс
            $cachedBalance = $account->getBalance();

            // Реальный баланс из транзакций
            $realBalance = $this->transactionRepository->calculateBalance($account);

            return $this->json([
                'accountId' => $account->getId()->toRfc4122(),
                'accountName' => $account->getName(),
                'currency' => $account->getCurrency()->value,
                'balance' => [
                    'cached' => [
                        'amount' => $cachedBalance->toFloat(),
                        'formatted' => $cachedBalance->format(),
                        'minorUnits' => $cachedBalance->getMinorUnits(),
                    ],
                    'calculated' => [
                        'amount' => $realBalance->toFloat(),
                        'formatted' => $realBalance->format(),
                        'minorUnits' => $realBalance->getMinorUnits(),
                    ],
                    'inSync' => $cachedBalance->equals($realBalance),
                ]
            ]);

        } catch (\Exception $e) {
            return $this->json([
                'error' => 'Failed to get balance',
                'message' => $e->getMessage()
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    /**
     * Форматирование транзакции для API ответа
     */
    private function formatTransaction($transaction): array
    {
        return [
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
                'minorUnits' => $transaction->getAmount()->getMinorUnits(),
                'currency' => $transaction->getAmount()->getCurrency()->value,
            ],
            'type' => $transaction->getType()->value,
            'typeLabel' => $transaction->getType()->getLabel(),
            'status' => $transaction->getStatus()->value,
            'statusLabel' => $transaction->getStatus()->getLabel(),
            'isFinal' => $transaction->getStatus()->isFinal(),
            'isCompleted' => $transaction->isCompleted(),
            'isFailed' => $transaction->isFailed(),
            'isReversed' => $transaction->isReversed(),
            'idempotencyKey' => $transaction->getIdempotencyKey(),
            'initiatedBy' => [
                'id' => $transaction->getInitiatedBy()->getId()->toRfc4122(),
                'email' => $transaction->getInitiatedBy()->getEmail(),
            ],
            'description' => $transaction->getDescription(),
            'metadata' => $transaction->getMetadata(),
            'relatedEntity' => $transaction->getRelatedEntityId() ? [
                'id' => $transaction->getRelatedEntityId()->toRfc4122(),
                'type' => $transaction->getRelatedEntityType(),
            ] : null,
            'executedAt' => $transaction->getExecutedAt()->format('c'),
            'createdAt' => $transaction->getCreatedAt()->format('c'),
            'processedAt' => $transaction->getProcessedAt() ? $transaction->getProcessedAt()->format('c') : null,
            'failureReason' => $transaction->getFailureReason(),
        ];
    }

    /**
     * Gets or creates a system account for external operations (deposits/withdrawals).
     * System accounts have deterministic UUIDs per currency.
     */
    private function getOrCreateSystemAccount(\App\Shared\ValueObject\Currency $currency): Account
    {
        $systemAccountIds = [
            'RUB' => '00000000-0000-0000-0000-000000000001',
            'USD' => '00000000-0000-0000-0000-000000000002',
            'EUR' => '00000000-0000-0000-0000-000000000003',
            'CNY' => '00000000-0000-0000-0000-000000000004',
        ];

        $systemAccountId = Uuid::fromString($systemAccountIds[$currency->value]);

        $systemAccount = $this->accountRepository->find($systemAccountId);

        if ($systemAccount) {
            return $systemAccount;
        }

        $systemCompany = $this->companyRepository->findOneBy(['type' => 'factor']);
        if (!$systemCompany) {
            throw new \RuntimeException('System configuration error: no factor company found');
        }

        $systemAccount = Account::createWithId(
            id: $systemAccountId,
            name: "System account ({$currency->value})",
            ownerId: Uuid::fromString('00000000-0000-0000-0000-000000000000'),
            company: $systemCompany,
            currency: $currency
        );

        $this->entityManager->persist($systemAccount);
        $this->entityManager->flush();

        return $systemAccount;
    }
}
