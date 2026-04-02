<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\Warehouse;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\ORM\QueryBuilder;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Warehouse>
 */
class WarehouseRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Warehouse::class);
    }

    public function countAll(): int
    {
        return (int) $this->createQueryBuilder('w')
            ->select('COUNT(w.id)')
            ->getQuery()
            ->getSingleScalarResult();
    }

    public function createPaginatedQueryBuilder(
        ?string $search = null,
        ?int $companyId = null,
        ?string $active = null,
        ?int $warehouseTypeId = null,
    ): QueryBuilder {
        $qb = $this->createQueryBuilder('w')
            ->leftJoin('w.company', 'c')
            ->addSelect('c')
            ->leftJoin('w.warehouseType', 'wt')
            ->addSelect('wt');

        if ($search !== null && $search !== '') {
            $qb->andWhere('w.name LIKE :search OR w.code LIKE :search OR c.name LIKE :search')
                ->setParameter('search', '%' . $search . '%');
        }

        if ($companyId !== null) {
            $qb->andWhere('c.id = :companyId')
                ->setParameter('companyId', $companyId);
        }

        if ($active !== null && $active !== '') {
            $qb->andWhere('w.isActive = :active')
                ->setParameter('active', $active === '1');
        }

        if ($warehouseTypeId !== null) {
            $qb->andWhere('wt.id = :warehouseTypeId')
                ->setParameter('warehouseTypeId', $warehouseTypeId);
        }

        return $qb;
    }
}
