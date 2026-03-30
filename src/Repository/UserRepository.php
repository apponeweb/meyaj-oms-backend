<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\User;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\ORM\QueryBuilder;
use Doctrine\Persistence\ManagerRegistry;
use Symfony\Component\Security\Core\Exception\UnsupportedUserException;
use Symfony\Component\Security\Core\User\PasswordAuthenticatedUserInterface;
use Symfony\Component\Security\Core\User\PasswordUpgraderInterface;

/**
 * @extends ServiceEntityRepository<User>
 */
class UserRepository extends ServiceEntityRepository implements PasswordUpgraderInterface
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, User::class);
    }

    public function upgradePassword(PasswordAuthenticatedUserInterface $user, string $newHashedPassword): void
    {
        if (!$user instanceof User) {
            throw new UnsupportedUserException(sprintf('Instances of "%s" are not supported.', $user::class));
        }

        $user->setPassword($newHashedPassword);
        $this->getEntityManager()->persist($user);
        $this->getEntityManager()->flush();
    }

    public function createPaginatedQueryBuilder(?string $search = null, ?int $roleId = null, ?string $active = null): QueryBuilder
    {
        $qb = $this->createQueryBuilder('u')
            ->select('u');

        if ($search !== null && $search !== '') {
            $qb->andWhere('(u.name LIKE :search OR u.lastName LIKE :search OR u.email LIKE :search OR u.phone LIKE :search)')
                ->setParameter('search', '%' . $search . '%');
        }

        if ($roleId !== null) {
            $qb->andWhere('u.role = :roleId')
                ->setParameter('roleId', $roleId);
        }

        if ($active !== null && $active !== '') {
            $qb->andWhere('u.active = :active')
                ->setParameter('active', (int)$active);
        }

        return $qb;
    }
}
