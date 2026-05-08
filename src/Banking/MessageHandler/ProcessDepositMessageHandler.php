<?php

declare(strict_types=1);

namespace App\Banking\MessageHandler;

use App\Banking\Entity\Transaction;
use App\Banking\Entity\TransactionStatus;
use App\Banking\Message\ProcessDepositMessage;
use App\Banking\Repository\TransactionRepository;
use App\Banking\Service\BankIntegrationService;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
final class ProcessDepositMessageHandler
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly TransactionRepository $transactionRepository,
        private readonly BankIntegrationService $bankIntegrationService,
        private readonly LoggerInterface $logger
    ) {
    }

    public function __invoke(ProcessDepositMessage $message): void
    {
        $transactionId = $message->getTransactionId();
        $idempotencyKey = $message->getIdempotencyKey();

        $this->logger->info('[Async] Processing deposit', [
            'transactionId' => $transactionId,
            'idempotencyKey' => $idempotencyKey,
        ]);

        // Получаем транзакцию
        $transaction = $this->transactionRepository->find($transactionId);

        if (!$transaction) {
            $this->logger->error('[Async] Transaction not found', ['id' => $transactionId]);
            return;
        }

        // Защита от повторной обработки
        if ($transaction->getStatus() !== TransactionStatus::PENDING) {
            $this->logger->warning('[Async] Transaction already processed', [
                'id' => $transactionId,
                'status' => $transaction->getStatus()->value,
            ]);
            return;
        }

        // Меняем статус на PROCESSING
        $transaction->setStatus(TransactionStatus::PROCESSING);
        $this->entityManager->flush();

        try {
            // Вызов API банка с idempotency key
            $result = $this->bankIntegrationService->processDeposit(
                transaction: $transaction,
                idempotencyKey: $idempotencyKey
            );

            if ($result['success']) {
                // Успешно - завершаем транзакцию и изменяем балансы
                $transaction->markAsCompleted();
                $transaction->setProcessedAt(new \DateTimeImmutable());

                // Изменяем балансы счетов
                $creditAccount = $transaction->getCreditAccount();
                $creditAccount->increaseBalance($transaction->getAmount());

                // Системный счет может иметь отрицательный баланс
                $debitAccount = $transaction->getDebitAccount();
                if ($debitAccount) {
                    // Для системного счета не проверяем баланс
                }

                $this->logger->info('[Async] Deposit completed successfully', [
                    'transactionId' => $transactionId,
                    'bankReference' => $result['bankReference'] ?? null,
                ]);
            } else {
                // Ошибка от банка - помечаем как failed
                $transaction->setStatus(TransactionStatus::FAILED);
                $transaction->setFailureReason($result['error'] ?? 'Unknown bank error');

                $this->logger->error('[Async] Deposit failed', [
                    'transactionId' => $transactionId,
                    'error' => $result['error'] ?? 'Unknown',
                ]);
            }

            $this->entityManager->flush();

        } catch (\Exception $e) {
            // Временная ошибка (сеть, таймаут) - бросаем исключение
            // Messenger автоматически повторит обработку
            $this->logger->error('[Async] Deposit processing exception', [
                'transactionId' => $transactionId,
                'exception' => $e->getMessage(),
            ]);

            // Возвращаем статус в PENDING для повторной попытки
            $transaction->setStatus(TransactionStatus::PENDING);
            $this->entityManager->flush();

            throw $e; // Messenger сделает retry
        }
    }
}
