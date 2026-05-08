<?php

declare(strict_types=1);

namespace App\Identity\Repository;

use App\Identity\Entity\Permission;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;
use Symfony\Component\Uid\Uuid;

/**
 * @extends ServiceEntityRepository<Permission>
 */
class PermissionRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Permission::class);
    }

    public function save(Permission $permission, bool $flush = true): void
    {
        $this->getEntityManager()->persist($permission);

        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }

    public function remove(Permission $permission, bool $flush = true): void
    {
        $this->getEntityManager()->remove($permission);

        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }

    public function findById(Uuid $id): ?Permission
    {
        return $this->find($id);
    }

    public function findByName(string $name): ?Permission
    {
        return $this->findOneBy(['name' => $name]);
    }

    /**
     * @return Permission[]
     */
    public function findAllOrderedByName(): array
    {
        return $this->createQueryBuilder('p')
            ->orderBy('p.name', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * @return Permission[]
     */
    public function findByCategory(?string $category): array
    {
        $qb = $this->createQueryBuilder('p');

        if ($category !== null) {
            $qb->where('p.category = :category')
               ->setParameter('category', $category);
        }

        return $qb->orderBy('p.name', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * @return Permission[]
     */
    public function findByNames(array $names): array
    {
        return $this->createQueryBuilder('p')
            ->where('p.name IN (:names)')
            ->setParameter('names', $names)
            ->getQuery()
            ->getResult();
    }
}
