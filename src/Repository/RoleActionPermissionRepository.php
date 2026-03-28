<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\RoleActionPermission;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<RoleActionPermission>
 */
class RoleActionPermissionRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, RoleActionPermission::class);
    }

    /** @return RoleActionPermission[] */
    public function findByRoleAndModule(int $roleId, int $moduleId): array
    {
        return $this->createQueryBuilder('rap')
            ->join('rap.action', 'a')
            ->where('rap.role = :roleId')
            ->andWhere('rap.appModule = :moduleId')
            ->andWhere('rap.allowed = true')
            ->setParameter('roleId', $roleId)
            ->setParameter('moduleId', $moduleId)
            ->getQuery()
            ->getResult();
    }
}
