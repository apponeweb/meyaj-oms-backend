<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\InventorySnapshot;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\ORM\QueryBuilder;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<InventorySnapshot>
 */
class InventorySnapshotRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, InventorySnapshot::class);
    }

    public function createPaginatedQueryBuilder(
        ?int $companyId = null,
        ?int $warehouseId = null,
        ?string $dateFrom = null,
        ?string $dateTo = null,
    ): QueryBuilder {
        $qb = $this->createQueryBuilder('s')
            ->leftJoin('s.company', 'c')
            ->leftJoin('s.warehouse', 'w')
            ->addSelect('c', 'w');

        if ($companyId !== null) {
            $qb->andWhere('c.id = :companyId')->setParameter('companyId', $companyId);
        }

        if ($warehouseId !== null) {
            $qb->andWhere('w.id = :warehouseId')->setParameter('warehouseId', $warehouseId);
        }

        if ($dateFrom !== null && $dateFrom !== '') {
            $qb->andWhere('s.snapshotDate >= :dateFrom')->setParameter('dateFrom', $dateFrom);
        }

        if ($dateTo !== null && $dateTo !== '') {
            $qb->andWhere('s.snapshotDate <= :dateTo')->setParameter('dateTo', $dateTo);
        }

        return $qb;
    }
}
