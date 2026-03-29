<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\UserSession;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<UserSession>
 */
class UserSessionRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, UserSession::class);
    }

    /**
     * Deactivate all active sessions for a given user.
     */
    public function deactivateAllForUser(\App\Entity\User $user): int
    {
        return $this->createQueryBuilder('s')
            ->update()
            ->set('s.active', 'false')
            ->set('s.logoutAt', ':now')
            ->where('s.user = :user')
            ->andWhere('s.active = true')
            ->setParameter('user', $user)
            ->setParameter('now', new \DateTimeImmutable())
            ->getQuery()
            ->execute();
    }

    public function findActiveByToken(string $token): ?UserSession
    {
        return $this->createQueryBuilder('s')
            ->where('s.token = :token')
            ->andWhere('s.active = true')
            ->setParameter('token', $token)
            ->getQuery()
            ->getOneOrNullResult();
    }

    /**
     * @return UserSession[]
     */
    public function findActiveSessions(int $timeoutMinutes): array
    {
        $threshold = new \DateTimeImmutable("-{$timeoutMinutes} minutes");

        return $this->createQueryBuilder('s')
            ->where('s.active = true')
            ->andWhere('s.lastActivityAt >= :threshold')
            ->setParameter('threshold', $threshold)
            ->orderBy('s.lastActivityAt', 'DESC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Mark sessions as expired if inactive for longer than timeout.
     */
    public function expireInactiveSessions(int $timeoutMinutes): int
    {
        $threshold = new \DateTimeImmutable("-{$timeoutMinutes} minutes");

        return $this->createQueryBuilder('s')
            ->update()
            ->set('s.active', 'false')
            ->set('s.logoutAt', ':now')
            ->where('s.active = true')
            ->andWhere('s.lastActivityAt < :threshold')
            ->setParameter('threshold', $threshold)
            ->setParameter('now', new \DateTimeImmutable())
            ->getQuery()
            ->execute();
    }

    /**
     * @return UserSession[]
     */
    public function findAllRecent(int $limit = 50): array
    {
        return $this->createQueryBuilder('s')
            ->orderBy('s.loginAt', 'DESC')
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();
    }
}
