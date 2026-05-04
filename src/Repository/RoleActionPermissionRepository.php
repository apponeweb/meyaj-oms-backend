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
    public function findByRoleAndFunction(int $roleId, int $functionId): array
    {
        return $this->createQueryBuilder('rap')
            ->join('rap.action', 'a')
            ->where('rap.role = :roleId')
            ->andWhere('rap.appFunction = :functionId')
            ->andWhere('rap.allowed = true')
            ->setParameter('roleId', $roleId)
            ->setParameter('functionId', $functionId)
            ->getQuery()
            ->getResult();
    }

    public function roleHasAllowedActionByCodes(int $roleId, string $functionCode, string $actionCode): bool
    {
        $count = $this->createQueryBuilder('rap')
            ->select('COUNT(rap.id)')
            ->join('rap.action', 'a')
            ->join('rap.appFunction', 'f')
            ->where('rap.role = :roleId')
            ->andWhere('f.code = :functionCode')
            ->andWhere('a.code = :actionCode')
            ->andWhere('rap.allowed = true')
            ->setParameter('roleId', $roleId)
            ->setParameter('functionCode', $functionCode)
            ->setParameter('actionCode', $actionCode)
            ->getQuery()
            ->getSingleScalarResult();

        return (int) $count > 0;
    }

    /** @return RoleActionPermission[] */
    public function findByRoleAndModuleFunctions(int $roleId, int $moduleId): array
    {
        return $this->createQueryBuilder('rap')
            ->join('rap.appFunction', 'f')
            ->where('rap.role = :roleId')
            ->andWhere('f.appModule = :moduleId')
            ->andWhere('rap.allowed = true')
            ->setParameter('roleId', $roleId)
            ->setParameter('moduleId', $moduleId)
            ->getQuery()
            ->getResult();
    }
}
