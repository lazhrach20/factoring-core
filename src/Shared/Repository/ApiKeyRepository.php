<?php

declare(strict_types=1);

namespace App\Shared\Repository;

use App\Identity\Entity\User;
use App\Shared\Entity\ApiKey;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;
use Symfony\Component\Uid\Uuid;

/**
 * @extends ServiceEntityRepository<ApiKey>
 */
class ApiKeyRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, ApiKey::class);
    }

    public function save(ApiKey $apiKey, bool $flush = false): void
    {
        $this->getEntityManager()->persist($apiKey);

        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }

    public function remove(ApiKey $apiKey, bool $flush = false): void
    {
        $this->getEntityManager()->remove($apiKey);

        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }

    public function findById(Uuid $id): ?ApiKey
    {
        return $this->find($id);
    }

    public function findByKeyHash(string $keyHash): ?ApiKey
    {
        return $this->findOneBy(['keyHash' => $keyHash]);
    }

    public function findActiveByKeyHash(string $keyHash): ?ApiKey
    {
        return $this->findOneBy([
            'keyHash' => $keyHash,
            'isActive' => true,
        ]);
    }

    /**
     * @return ApiKey[]
     */
    public function findByUser(User $user): array
    {
        return $this->findBy(['user' => $user], ['createdAt' => 'DESC']);
    }

    /**
     * @return ApiKey[]
     */
    public function findActiveByUser(User $user): array
    {
        return $this->findBy(
            ['user' => $user, 'isActive' => true],
            ['createdAt' => 'DESC']
        );
    }

    public function countByUser(User $user): int
    {
        return $this->count(['user' => $user]);
    }

    public function countActiveByUser(User $user): int
    {
        return $this->count(['user' => $user, 'isActive' => true]);
    }
}
