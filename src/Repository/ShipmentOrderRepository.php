<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\ShipmentOrder;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\ORM\QueryBuilder;
use Doctrine\Persistence\ManagerRegistry;

/** @extends ServiceEntityRepository<ShipmentOrder> */
class ShipmentOrderRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry) { parent::__construct($registry, ShipmentOrder::class); }

    public function createPaginatedQueryBuilder(
        ?string $search = null,
        ?string $status = null,
        ?int $warehouseId = null,
        ?int $salesOrderId = null,
    ): QueryBuilder {
        $qb = $this->createQueryBuilder('sho')
            ->leftJoin('sho.salesOrder', 'so')
            ->leftJoin('sho.warehouse', 'w')
            ->leftJoin('sho.createdBy', 'cb')
            ->addSelect('so', 'w', 'cb');

        if ($search !== null && $search !== '') {
            $qb->andWhere('sho.folio LIKE :search OR sho.trackingNumber LIKE :search OR so.folio LIKE :search')
                ->setParameter('search', '%' . $search . '%');
        }

        if ($status !== null && $status !== '') {
            $qb->andWhere('sho.status = :status')
                ->setParameter('status', $status);
        }

        if ($warehouseId !== null) {
            $qb->andWhere('sho.warehouse = :warehouseId')
                ->setParameter('warehouseId', $warehouseId);
        }

        if ($salesOrderId !== null) {
            $qb->andWhere('sho.salesOrder = :salesOrderId')
                ->setParameter('salesOrderId', $salesOrderId);
        }

        return $qb;
    }
}
