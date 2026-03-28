<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\RoleModulePermission;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<RoleModulePermission>
 */
class RoleModulePermissionRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, RoleModulePermission::class);
    }

    /** @return RoleModulePermission[] */
    public function findByRoleId(int $roleId): array
    {
        return $this->createQueryBuilder('rmp')
            ->join('rmp.appModule', 'm')
            ->where('rmp.role = :roleId')
            ->andWhere('rmp.canAccess = true')
            ->andWhere('m.active = true')
            ->setParameter('roleId', $roleId)
            ->orderBy('m.displayOrder', 'ASC')
            ->getQuery()
            ->getResult();
    }
}
