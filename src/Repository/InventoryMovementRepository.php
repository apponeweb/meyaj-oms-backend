<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\InventoryMovement;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\ORM\QueryBuilder;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<InventoryMovement>
 */
class InventoryMovementRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, InventoryMovement::class);
    }

    public function createPaginatedQueryBuilder(
        ?string $search = null,
        ?int $pacaId = null,
        ?int $warehouseId = null,
        ?string $movementType = null,
        ?string $dateFrom = null,
        ?string $dateTo = null,
    ): QueryBuilder {
        $qb = $this->createQueryBuilder('m')
            ->leftJoin('m.paca', 'p')
            ->addSelect('p')
            ->leftJoin('m.warehouse', 'w')
            ->addSelect('w')
            ->leftJoin('m.warehouseBin', 'wb')
            ->addSelect('wb')
            ->leftJoin('m.reason', 'r')
            ->addSelect('r')
            ->leftJoin('m.user', 'u')
            ->addSelect('u')
            ->orderBy('m.createdAt', 'DESC')
            ->addOrderBy('m.id', 'DESC');

        if ($search !== null && $search !== '') {
            $qb->andWhere('p.code LIKE :search OR p.name LIKE :search OR r.name LIKE :search')
                ->setParameter('search', '%' . $search . '%');
        }

        if ($pacaId !== null) {
            $qb->andWhere('p.id = :pacaId')
                ->setParameter('pacaId', $pacaId);
        }

        if ($warehouseId !== null) {
            $qb->andWhere('w.id = :warehouseId')
                ->setParameter('warehouseId', $warehouseId);
        }

        if ($movementType !== null && $movementType !== '') {
            $qb->andWhere('m.movementType = :movementType')
                ->setParameter('movementType', $movementType);
        }

        if ($dateFrom !== null && $dateFrom !== '') {
            $qb->andWhere('m.createdAt >= :dateFrom')
                ->setParameter('dateFrom', $dateFrom);
        }

        if ($dateTo !== null && $dateTo !== '') {
            $qb->andWhere('m.createdAt <= :dateTo')
                ->setParameter('dateTo', $dateTo);
        }

        return $qb;
    }
}
