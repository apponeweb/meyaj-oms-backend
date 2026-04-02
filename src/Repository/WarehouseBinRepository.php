<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\WarehouseBin;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\ORM\QueryBuilder;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<WarehouseBin>
 */
class WarehouseBinRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, WarehouseBin::class);
    }

    public function countAll(): int
    {
        return (int) $this->createQueryBuilder('b')
            ->select('COUNT(b.id)')
            ->getQuery()
            ->getSingleScalarResult();
    }

    public function createPaginatedQueryBuilder(
        ?string $search = null,
        ?int $warehouseId = null,
        ?string $active = null,
    ): QueryBuilder {
        $qb = $this->createQueryBuilder('b')
            ->leftJoin('b.warehouse', 'w')
            ->addSelect('w');

        if ($search !== null && $search !== '') {
            $qb->andWhere('b.name LIKE :search OR b.code LIKE :search OR b.zone LIKE :search')
                ->setParameter('search', '%' . $search . '%');
        }

        if ($warehouseId !== null) {
            $qb->andWhere('w.id = :warehouseId')
                ->setParameter('warehouseId', $warehouseId);
        }

        if ($active !== null && $active !== '') {
            $qb->andWhere('b.isActive = :active')
                ->setParameter('active', $active === '1');
        }

        return $qb;
    }
}
