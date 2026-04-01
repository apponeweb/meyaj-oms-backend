<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\SalesOrder;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\ORM\QueryBuilder;
use Doctrine\Persistence\ManagerRegistry;

/** @extends ServiceEntityRepository<SalesOrder> */
class SalesOrderRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry) { parent::__construct($registry, SalesOrder::class); }

    public function createPaginatedQueryBuilder(
        ?string $search = null,
        ?int $customerId = null,
        ?string $status = null,
        ?string $channel = null,
        ?string $paymentStatus = null,
        ?int $companyId = null,
    ): QueryBuilder {
        $qb = $this->createQueryBuilder('so')
            ->leftJoin('so.company', 'c')
            ->leftJoin('so.customer', 'cu')
            ->leftJoin('so.user', 'u')
            ->leftJoin('so.seller', 'se')
            ->addSelect('c', 'cu', 'u', 'se');

        if ($search !== null && $search !== '') {
            $qb->andWhere('so.folio LIKE :search OR cu.name LIKE :search')
                ->setParameter('search', '%' . $search . '%');
        }

        if ($customerId !== null) {
            $qb->andWhere('so.customer = :customerId')
                ->setParameter('customerId', $customerId);
        }

        if ($status !== null && $status !== '') {
            $qb->andWhere('so.status = :status')
                ->setParameter('status', $status);
        }

        if ($channel !== null && $channel !== '') {
            $qb->andWhere('so.channel = :channel')
                ->setParameter('channel', $channel);
        }

        if ($paymentStatus !== null && $paymentStatus !== '') {
            $qb->andWhere('so.paymentStatus = :paymentStatus')
                ->setParameter('paymentStatus', $paymentStatus);
        }

        if ($companyId !== null) {
            $qb->andWhere('so.company = :companyId')
                ->setParameter('companyId', $companyId);
        }

        return $qb;
    }
}
