<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\InventoryCount;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\ORM\QueryBuilder;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<InventoryCount>
 */
class InventoryCountRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, InventoryCount::class);
    }

    public function createPaginatedQueryBuilder(
        ?string $search = null,
        ?int $warehouseId = null,
        ?string $status = null,
        ?int $companyId = null,
    ): QueryBuilder {
        $qb = $this->createQueryBuilder('c')
            ->leftJoin('c.warehouse', 'w')
            ->addSelect('w')
            ->leftJoin('c.company', 'co')
            ->addSelect('co')
            ->leftJoin('c.user', 'u')
            ->addSelect('u');

        if ($search !== null && $search !== '') {
            $qb->andWhere('c.folio LIKE :search OR w.name LIKE :search OR u.name LIKE :search')
                ->setParameter('search', '%' . $search . '%');
        }

        if ($warehouseId !== null) {
            $qb->andWhere('w.id = :warehouseId')
                ->setParameter('warehouseId', $warehouseId);
        }

        if ($status !== null && $status !== '') {
            $qb->andWhere('c.status = :status')
                ->setParameter('status', $status);
        }

        if ($companyId !== null) {
            $qb->andWhere('co.id = :companyId')
                ->setParameter('companyId', $companyId);
        }

        return $qb;
    }
}
