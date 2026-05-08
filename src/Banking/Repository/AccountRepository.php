<?php

declare(strict_types=1);

namespace App\Banking\Repository;

use App\Banking\Entity\Account;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\DBAL\LockMode;
use Doctrine\Persistence\ManagerRegistry;
use Symfony\Component\Uid\Uuid;

/**
 * Repository for Account entity with pessimistic locking support
 *
 * @extends ServiceEntityRepository<Account>
 */
class AccountRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Account::class);
    }

    /**
     * Find an account by ID with pessimistic write lock (SELECT ... FOR UPDATE)
     * This prevents concurrent modifications and race conditions during money transfers
     *
     * IMPORTANT: This method must be called within an active database transaction
     * The lock will be held until the transaction is committed or rolled back
     *
     * @param Uuid $id Account UUID
     * @return Account|null The locked account or null if not found
     * @throws \Doctrine\DBAL\Exception If called outside a transaction
     */
    public function findWithLock(Uuid $id): ?Account
    {
        return $this->find($id, LockMode::PESSIMISTIC_WRITE);
    }

    /**
     * Find an account by owner ID
     *
     * @param Uuid $ownerId Owner UUID
     * @return Account|null
     */
    public function findByOwnerId(Uuid $ownerId): ?Account
    {
        return $this->findOneBy(['ownerId' => $ownerId]);
    }

    /**
     * Find all accounts for a specific owner
     *
     * @param Uuid $ownerId Owner UUID
     * @return Account[]
     */
    public function findAllByOwnerId(Uuid $ownerId): array
    {
        return $this->findBy(['ownerId' => $ownerId]);
    }

    /**
     * Find all accounts for a specific company
     *
     * @param \App\Identity\Entity\Company $company Company entity
     * @return Account[]
     */
    public function findByCompany($company): array
    {
        return $this->findBy(['company' => $company], ['name' => 'ASC']);
    }

    /**
     * Save account entity
     *
     * @param Account $account
     * @param bool $flush Whether to flush immediately
     */
    public function save(Account $account, bool $flush = false): void
    {
        $this->getEntityManager()->persist($account);

        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }

    /**
     * Remove account entity
     *
     * @param Account $account
     * @param bool $flush Whether to flush immediately
     */
    public function remove(Account $account, bool $flush = false): void
    {
        $this->getEntityManager()->remove($account);

        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }
}
