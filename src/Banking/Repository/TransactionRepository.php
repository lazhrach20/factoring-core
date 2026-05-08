<?php

declare(strict_types=1);

namespace App\Banking\Repository;

use App\Banking\Entity\Account;
use App\Banking\Entity\Transaction;
use App\Shared\ValueObject\Currency;
use App\Shared\ValueObject\Money;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Transaction>
 */
class TransactionRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Transaction::class);
    }

    /**
     * Найти транзакцию по ключу идемпотентности
     * Используется для предотвращения двойных операций
     */
    public function findByIdempotencyKey(string $idempotencyKey): ?Transaction
    {
        return $this->findOneBy(['idempotencyKey' => $idempotencyKey]);
    }

    /**
     * Получить транзакции по счету (дебет или кредит)
     *
     * @return Transaction[]
     */
    public function findByAccount(Account $account, int $limit = 100, int $offset = 0): array
    {
        return $this->createQueryBuilder('t')
            ->where('t.debitAccount = :account OR t.creditAccount = :account')
            ->setParameter('account', $account)
            ->orderBy('t.executedAt', 'DESC')
            ->setMaxResults($limit)
            ->setFirstResult($offset)
            ->getQuery()
            ->getResult();
    }

    /**
     * Получить транзакции дебета (списание со счета)
     *
     * @return Transaction[]
     */
    public function findDebitTransactions(Account $account, int $limit = 100): array
    {
        return $this->createQueryBuilder('t')
            ->where('t.debitAccount = :account')
            ->andWhere('t.status = :completed')
            ->setParameter('account', $account)
            ->setParameter('completed', 'completed')
            ->orderBy('t.executedAt', 'DESC')
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();
    }

    /**
     * Получить транзакции кредита (поступление на счет)
     *
     * @return Transaction[]
     */
    public function findCreditTransactions(Account $account, int $limit = 100): array
    {
        return $this->createQueryBuilder('t')
            ->where('t.creditAccount = :account')
            ->andWhere('t.status = :completed')
            ->setParameter('account', $account)
            ->setParameter('completed', 'completed')
            ->orderBy('t.executedAt', 'DESC')
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();
    }

    /**
     * Рассчитать баланс счета из истории транзакций
     *
     * Формула: balance = SUM(credit) - SUM(debit)
     * где credit - поступления на счет, debit - списания со счета
     */
    public function calculateBalance(Account $account): Money
    {
        $qb = $this->createQueryBuilder('t');

        // Сумма поступлений (кредит)
        $creditSum = (int) $qb->select('COALESCE(SUM(t.amountMinorUnits), 0)')
            ->where('t.creditAccount = :account')
            ->andWhere('t.status = :completed')
            ->setParameter('account', $account)
            ->setParameter('completed', 'completed')
            ->getQuery()
            ->getSingleScalarResult();

        // Сумма списаний (дебет)
        $debitSum = (int) $qb->select('COALESCE(SUM(t.amountMinorUnits), 0)')
            ->where('t.debitAccount = :account')
            ->andWhere('t.status = :completed')
            ->setParameter('account', $account)
            ->setParameter('completed', 'completed')
            ->getQuery()
            ->getSingleScalarResult();

        $balanceMinorUnits = $creditSum - $debitSum;

        $currency = $account->getCurrency();

        return Money::fromMinorUnits($balanceMinorUnits, $currency);
    }

    public function save(Transaction $transaction): void
    {
        $this->getEntityManager()->persist($transaction);
    }

    public function flush(): void
    {
        $this->getEntityManager()->flush();
    }
}
