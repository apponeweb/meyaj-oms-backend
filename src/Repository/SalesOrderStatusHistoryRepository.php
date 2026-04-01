<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\SalesOrderStatusHistory;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/** @extends ServiceEntityRepository<SalesOrderStatusHistory> */
class SalesOrderStatusHistoryRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, SalesOrderStatusHistory::class);
    }

    /**
     * @return SalesOrderStatusHistory[]
     */
    public function findByOrder(int $salesOrderId): array
    {
        return $this->createQueryBuilder('h')
            ->leftJoin('h.user', 'u')
            ->addSelect('u')
            ->andWhere('h.salesOrder = :orderId')
            ->setParameter('orderId', $salesOrderId)
            ->orderBy('h.createdAt', 'ASC')
            ->getQuery()
            ->getResult();
    }
}
