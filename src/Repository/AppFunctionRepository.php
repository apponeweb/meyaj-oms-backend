<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\AppFunction;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<AppFunction>
 */
class AppFunctionRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, AppFunction::class);
    }

    /** @return AppFunction[] */
    public function findByModuleId(int $moduleId): array
    {
        return $this->createQueryBuilder('f')
            ->where('f.appModule = :moduleId')
            ->andWhere('f.active = true')
            ->setParameter('moduleId', $moduleId)
            ->orderBy('f.displayOrder', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /** @return AppFunction[] */
    public function findAllActive(): array
    {
        return $this->createQueryBuilder('f')
            ->join('f.appModule', 'm')
            ->where('f.active = true')
            ->andWhere('m.active = true')
            ->orderBy('m.displayOrder', 'ASC')
            ->addOrderBy('f.displayOrder', 'ASC')
            ->getQuery()
            ->getResult();
    }
}
