<?php

namespace App\Shared\Repository;

use App\Identity\Entity\User;
use App\Shared\Entity\AuditLog;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<AuditLog>
 */
class AuditLogRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, AuditLog::class);
    }

    public function save(AuditLog $auditLog, bool $flush = true): void
    {
        $this->getEntityManager()->persist($auditLog);

        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }

    /**
     * Find audit logs with filters
     *
     * @param array $filters - ['user' => User, 'action' => string, 'startDate' => DateTime, 'endDate' => DateTime]
     * @param int $page
     * @param int $limit
     * @return array
     */
    public function findWithFilters(array $filters = [], int $page = 1, int $limit = 50): array
    {
        $qb = $this->createQueryBuilder('a')
            ->leftJoin('a.user', 'u')
            ->addSelect('u')
            ->orderBy('a.createdAt', 'DESC');

        if (isset($filters['user']) && $filters['user'] instanceof User) {
            $qb->andWhere('a.user = :user')
                ->setParameter('user', $filters['user']);
        }

        if (isset($filters['action']) && !empty($filters['action'])) {
            $qb->andWhere('a.action LIKE :action')
                ->setParameter('action', '%' . $filters['action'] . '%');
        }

        if (isset($filters['entityType']) && !empty($filters['entityType'])) {
            $qb->andWhere('a.entityType = :entityType')
                ->setParameter('entityType', $filters['entityType']);
        }

        if (isset($filters['startDate']) && $filters['startDate'] instanceof \DateTimeInterface) {
            $qb->andWhere('a.createdAt >= :startDate')
                ->setParameter('startDate', $filters['startDate']);
        }

        if (isset($filters['endDate']) && $filters['endDate'] instanceof \DateTimeInterface) {
            $qb->andWhere('a.createdAt <= :endDate')
                ->setParameter('endDate', $filters['endDate']);
        }

        if (isset($filters['httpMethod']) && !empty($filters['httpMethod'])) {
            $qb->andWhere('a.httpMethod = :httpMethod')
                ->setParameter('httpMethod', $filters['httpMethod']);
        }

        if (isset($filters['statusCode'])) {
            if ($filters['statusCode'] === 'error') {
                $qb->andWhere('a.statusCode >= 400');
            } elseif (is_numeric($filters['statusCode'])) {
                $qb->andWhere('a.statusCode = :statusCode')
                    ->setParameter('statusCode', (int)$filters['statusCode']);
            }
        }

        $offset = ($page - 1) * $limit;
        $qb->setFirstResult($offset)
            ->setMaxResults($limit);

        return $qb->getQuery()->getResult();
    }

    /**
     * Count audit logs with filters
     */
    public function countWithFilters(array $filters = []): int
    {
        $qb = $this->createQueryBuilder('a')
            ->select('COUNT(a.id)');

        if (isset($filters['user']) && $filters['user'] instanceof User) {
            $qb->andWhere('a.user = :user')
                ->setParameter('user', $filters['user']);
        }

        if (isset($filters['action']) && !empty($filters['action'])) {
            $qb->andWhere('a.action LIKE :action')
                ->setParameter('action', '%' . $filters['action'] . '%');
        }

        if (isset($filters['entityType']) && !empty($filters['entityType'])) {
            $qb->andWhere('a.entityType = :entityType')
                ->setParameter('entityType', $filters['entityType']);
        }

        if (isset($filters['startDate']) && $filters['startDate'] instanceof \DateTimeInterface) {
            $qb->andWhere('a.createdAt >= :startDate')
                ->setParameter('startDate', $filters['startDate']);
        }

        if (isset($filters['endDate']) && $filters['endDate'] instanceof \DateTimeInterface) {
            $qb->andWhere('a.createdAt <= :endDate')
                ->setParameter('endDate', $filters['endDate']);
        }

        if (isset($filters['httpMethod']) && !empty($filters['httpMethod'])) {
            $qb->andWhere('a.httpMethod = :httpMethod')
                ->setParameter('httpMethod', $filters['httpMethod']);
        }

        if (isset($filters['statusCode'])) {
            if ($filters['statusCode'] === 'error') {
                $qb->andWhere('a.statusCode >= 400');
            } elseif (is_numeric($filters['statusCode'])) {
                $qb->andWhere('a.statusCode = :statusCode')
                    ->setParameter('statusCode', (int)$filters['statusCode']);
            }
        }

        return (int) $qb->getQuery()->getSingleScalarResult();
    }

    /**
     * Find recent logs for a user
     */
    public function findRecentByUser(User $user, int $limit = 20): array
    {
        return $this->createQueryBuilder('a')
            ->where('a.user = :user')
            ->setParameter('user', $user)
            ->orderBy('a.createdAt', 'DESC')
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();
    }

    /**
     * Find logs by action type
     */
    public function findByAction(string $action, int $limit = 50): array
    {
        return $this->createQueryBuilder('a')
            ->leftJoin('a.user', 'u')
            ->addSelect('u')
            ->where('a.action = :action')
            ->setParameter('action', $action)
            ->orderBy('a.createdAt', 'DESC')
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();
    }
}
