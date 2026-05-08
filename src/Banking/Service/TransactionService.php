<?php

declare(strict_types=1);

namespace App\Banking\Service;

use App\Banking\Entity\Account;
use App\Banking\Entity\Transaction;
use App\Banking\Entity\TransactionType;
use App\Banking\Repository\TransactionRepository;
use App\Identity\Entity\User;
use App\Shared\Exception\InsufficientFundsException;
use App\Shared\ValueObject\Money;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Uid\Uuid;

/**
 * Сервис для работы с транзакциями
 *
 * Реализует двойную запись (double-entry bookkeeping):
 * - Каждая транзакция имеет счет-источник (debit) и счет-получатель (credit)
 * - Балансы счетов рассчитываются из истории транзакций
 * - Поддерживает идемпотентность для предотвращения двойных операций
 */
class TransactionService
{
    public function __construct(
        private readonly TransactionRepository $transactionRepository,
        private readonly EntityManagerInterface $entityManager,
    ) {
    }

    /**
     * Выполнить перевод между счетами
     *
     * @param Account $from Счет-источник (debit account)
     * @param Account $to Счет-получатель (credit account)
     * @param Money $amount Сумма перевода
     * @param TransactionType $type Тип транзакции
     * @param string $idempotencyKey Ключ идемпотентности (UUID строка)
     * @param User $initiatedBy Пользователь, инициировавший операцию
     * @param string|null $description Описание транзакции
     * @param array|null $metadata Дополнительные данные
     * @param Uuid|null $relatedEntityId ID связанной сущности (FinancingRequest, Invoice, etc)
     * @param string|null $relatedEntityType Тип связанной сущности
     *
     * @return Transaction Созданная или существующая транзакция
     *
     * @throws InsufficientFundsException Если на счете недостаточно средств
     */
    public function transfer(
        Account $from,
        Account $to,
        Money $amount,
        TransactionType $type,
        string $idempotencyKey,
        User $initiatedBy,
        ?string $description = null,
        ?array $metadata = null,
        ?Uuid $relatedEntityId = null,
        ?string $relatedEntityType = null
    ): Transaction {
        // 1. Проверить идемпотентность - возможно транзакция уже была создана
        $existingTransaction = $this->transactionRepository->findByIdempotencyKey($idempotencyKey);
        if ($existingTransaction !== null) {
            return $existingTransaction;
        }

        // 2. Проверить достаточность средств на счете-источнике
        $currentBalance = $this->transactionRepository->calculateBalance($from);
        if ($currentBalance->lessThan($amount)) {
            throw new InsufficientFundsException($amount, $currentBalance);
        }

        // 3. Начать транзакцию БД для атомарности операции
        $this->entityManager->beginTransaction();

        try {
            // 4. Создать транзакцию
            $transaction = Transaction::create(
                id: Uuid::v4(),
                debitAccount: $from,
                creditAccount: $to,
                amount: $amount,
                type: $type,
                idempotencyKey: $idempotencyKey,
                initiatedBy: $initiatedBy,
                description: $description,
                metadata: $metadata,
                relatedEntityId: $relatedEntityId,
                relatedEntityType: $relatedEntityType
            );

            // 5. Сохранить транзакцию
            $this->transactionRepository->save($transaction);

            // 6. Обновить кэшированные балансы счетов
            $from->decreaseBalance($amount);
            $to->increaseBalance($amount);

            // 7. Завершить транзакцию
            $transaction->markAsCompleted();

            // 8. Зафиксировать изменения
            $this->entityManager->flush();
            $this->entityManager->commit();

            return $transaction;

        } catch (\Exception $e) {
            // Откатить транзакцию при любой ошибке
            $this->entityManager->rollback();
            throw $e;
        }
    }

    /**
     * Отменить (реверсировать) транзакцию
     *
     * Создает обратную транзакцию и отмечает исходную как reversed
     */
    public function reverse(
        Transaction $original,
        User $initiatedBy,
        string $reason,
        string $idempotencyKey
    ): Transaction {
        if (!$original->isCompleted()) {
            throw new \LogicException('Can only reverse completed transactions');
        }

        // Проверить идемпотентность
        $existingReversal = $this->transactionRepository->findByIdempotencyKey($idempotencyKey);
        if ($existingReversal !== null) {
            return $existingReversal;
        }

        $this->entityManager->beginTransaction();

        try {
            // Создать обратную транзакцию (меняем местами debit/credit)
            $reversal = Transaction::create(
                id: Uuid::v4(),
                debitAccount: $original->getCreditAccount(), // Было получателем, стало источником
                creditAccount: $original->getDebitAccount(), // Было источником, стало получателем
                amount: $original->getAmount(),
                type: $original->getType(),
                idempotencyKey: $idempotencyKey,
                initiatedBy: $initiatedBy,
                description: "Reversal: {$reason}",
                metadata: [
                    'reversal_of' => $original->getId()->toRfc4122(),
                    'reason' => $reason,
                ],
                relatedEntityId: $original->getRelatedEntityId(),
                relatedEntityType: $original->getRelatedEntityType()
            );

            $this->transactionRepository->save($reversal);
            $reversal->markAsCompleted();

            // Отметить оригинальную транзакцию как reversed
            $original->reverse($reason);

            $this->entityManager->flush();
            $this->entityManager->commit();

            return $reversal;

        } catch (\Exception $e) {
            $this->entityManager->rollback();
            throw $e;
        }
    }
}
