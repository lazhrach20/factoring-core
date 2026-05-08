<?php

declare(strict_types=1);

namespace App\Banking\Repository;

use App\Banking\Entity\FinancingRequest;
use App\Banking\Entity\FinancingRequestStatus;
use App\Identity\Entity\Company;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;
use Symfony\Component\Uid\Uuid;

/**
 * @extends ServiceEntityRepository<FinancingRequest>
 */
class FinancingRequestRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, FinancingRequest::class);
    }

    /**
     * Найти заявки доступные для компании
     */
    public function findByCompany(Company $company, ?FinancingRequestStatus $status = null, int $limit = 50): array
    {
        $qb = $this->createQueryBuilder('fr')
            ->where('fr.supplierCompany = :company')
            ->orWhere('fr.debtorCompany = :company')
            ->orWhere('fr.factorAccount IN (
                SELECT a FROM App\Banking\Entity\Account a WHERE a.company = :company
            )')
            ->setParameter('company', $company)
            ->orderBy('fr.createdAt', 'DESC')
            ->setMaxResults($limit);

        if ($status !== null) {
            $qb->andWhere('fr.status = :status')
                ->setParameter('status', $status);
        }

        return $qb->getQuery()->getResult();
    }

    /**
     * Найти заявки по статусу
     */
    public function findByStatus(FinancingRequestStatus $status, int $limit = 50): array
    {
        return $this->createQueryBuilder('fr')
            ->where('fr.status = :status')
            ->setParameter('status', $status)
            ->orderBy('fr.createdAt', 'DESC')
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();
    }

    /**
     * Подсчитать заявки по статусу для компании
     */
    public function countByCompanyAndStatus(Company $company, FinancingRequestStatus $status): int
    {
        return (int) $this->createQueryBuilder('fr')
            ->select('COUNT(fr.id)')
            ->where('fr.supplierCompany = :company OR fr.debtorCompany = :company')
            ->andWhere('fr.status = :status')
            ->setParameter('company', $company)
            ->setParameter('status', $status)
            ->getQuery()
            ->getSingleScalarResult();
    }
}
