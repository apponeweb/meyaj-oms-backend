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

    public function createPaginatedQueryBuilder(
        ?string $search = null,
        ?int $companyId = null,
        ?string $active = null,
        ?string $warehouseType = null,
    ): QueryBuilder {
        $qb = $this->createQueryBuilder('w')
            ->leftJoin('w.company', 'c')
            ->addSelect('c');

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

        if ($warehouseType !== null && $warehouseType !== '') {
            $qb->andWhere('w.warehouseType = :warehouseType')
                ->setParameter('warehouseType', $warehouseType);
        }

        return $qb;
    }
}
