<?php

declare(strict_types=1);

namespace App\Identity\Repository;

use App\Identity\Entity\PasswordResetToken;
use App\Identity\Entity\User;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<PasswordResetToken>
 */
class PasswordResetTokenRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, PasswordResetToken::class);
    }

    public function save(PasswordResetToken $token): void
    {
        $this->getEntityManager()->persist($token);
        $this->getEntityManager()->flush();
    }

    public function findValidToken(string $token): ?PasswordResetToken
    {
        $resetToken = $this->findOneBy(['token' => $token]);

        if ($resetToken && $resetToken->isValid()) {
            return $resetToken;
        }

        return null;
    }

    public function findLatestByUser(User $user): ?PasswordResetToken
    {
        return $this->createQueryBuilder('prt')
            ->where('prt.user = :user')
            ->setParameter('user', $user)
            ->orderBy('prt.createdAt', 'DESC')
            ->setMaxResults(1)
            ->getQuery()
            ->getOneOrNullResult();
    }

    public function deleteExpiredTokens(): int
    {
        return $this->createQueryBuilder('prt')
            ->delete()
            ->where('prt.expiresAt < :now')
            ->setParameter('now', new \DateTimeImmutable())
            ->getQuery()
            ->execute();
    }

    public function deleteByUser(User $user): int
    {
        return $this->createQueryBuilder('prt')
            ->delete()
            ->where('prt.user = :user')
            ->setParameter('user', $user)
            ->getQuery()
            ->execute();
    }
}
