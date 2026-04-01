<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\PurchaseOrder;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\ORM\QueryBuilder;
use Doctrine\Persistence\ManagerRegistry;

/** @extends ServiceEntityRepository<PurchaseOrder> */
class PurchaseOrderRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry) { parent::__construct($registry, PurchaseOrder::class); }

    public function createPaginatedQueryBuilder(
        ?string $search = null,
        ?int $supplierId = null,
        ?int $companyId = null,
        ?string $status = null,
    ): QueryBuilder {
        $qb = $this->createQueryBuilder('po')
            ->leftJoin('po.supplier', 's')
            ->leftJoin('po.company', 'c')
            ->leftJoin('po.user', 'u')
            ->addSelect('s', 'c', 'u');

        if ($search !== null && $search !== '') {
            $qb->andWhere('po.folio LIKE :search OR s.name LIKE :search')
                ->setParameter('search', '%' . $search . '%');
        }

        if ($supplierId !== null) {
            $qb->andWhere('po.supplier = :supplierId')
                ->setParameter('supplierId', $supplierId);
        }

        if ($companyId !== null) {
            $qb->andWhere('po.company = :companyId')
                ->setParameter('companyId', $companyId);
        }

        if ($status !== null && $status !== '') {
            $qb->andWhere('po.status = :status')
                ->setParameter('status', $status);
        }

        return $qb;
    }
}
