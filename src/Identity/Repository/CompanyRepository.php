<?php

declare(strict_types=1);

namespace App\Identity\Repository;

use App\Identity\Entity\Company;
use App\Identity\Entity\CompanyType;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;
use Symfony\Component\Uid\Uuid;

/**
 * Repository for Company entity
 *
 * @extends ServiceEntityRepository<Company>
 */
class CompanyRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Company::class);
    }

    public function save(Company $company, bool $flush = false): void
    {
        $this->getEntityManager()->persist($company);

        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }

    public function remove(Company $company, bool $flush = false): void
    {
        $this->getEntityManager()->remove($company);

        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }

    public function findById(Uuid $id): ?Company
    {
        return $this->find($id);
    }

    public function findByInn(string $inn): ?Company
    {
        return $this->findOneBy(['inn' => $inn]);
    }

    /**
     * Find companies by type
     *
     * @return Company[]
     */
    public function findByType(CompanyType $type): array
    {
        return $this->createQueryBuilder('c')
            ->where('c.type = :type')
            ->setParameter('type', $type->value)
            ->orderBy('c.name', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Find all active companies
     *
     * @return Company[]
     */
    public function findActive(): array
    {
        return $this->createQueryBuilder('c')
            ->where('c.status = :status')
            ->setParameter('status', 'active')
            ->orderBy('c.name', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Find all suppliers (clients)
     *
     * @return Company[]
     */
    public function findSuppliers(): array
    {
        return $this->findByType(CompanyType::SUPPLIER);
    }

    /**
     * Find all debtors
     *
     * @return Company[]
     */
    public function findDebtors(): array
    {
        return $this->findByType(CompanyType::DEBTOR);
    }

    /**
     * Check if INN already exists
     */
    public function innExists(string $inn): bool
    {
        return $this->count(['inn' => $inn]) > 0;
    }
}
