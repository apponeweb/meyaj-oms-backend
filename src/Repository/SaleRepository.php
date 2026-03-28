<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\Sale;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Sale>
 */
class SaleRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Sale::class);
    }

    /**
     * @return Sale[] Returns an array of Sale objects
     */
    public function findByDateRange(\DateTimeInterface $startDate, \DateTimeInterface $endDate): array
    {
        return $this->createQueryBuilder('s')
            ->where('s.createdAt >= :startDate')
            ->andWhere('s.createdAt <= :endDate')
            ->andWhere('s.status != :cancelled')
            ->setParameter('startDate', $startDate)
            ->setParameter('endDate', $endDate)
            ->setParameter('cancelled', Sale::STATUS_CANCELLED)
            ->orderBy('s.createdAt', 'DESC')
            ->getQuery()
            ->getResult()
        ;
    }

    public function findTodaySales(): array
    {
        $today = new \DateTimeImmutable('today');
        $tomorrow = $today->modify('+1 day');

        return $this->findByDateRange($today, $tomorrow);
    }

    public function getDailySalesTotal(\DateTimeInterface $date): string
    {
        $startDate = $date->setTime(0, 0, 0);
        $endDate = $date->setTime(23, 59, 59);

        $result = $this->createQueryBuilder('s')
            ->select('SUM(s.total) as total')
            ->where('s.createdAt >= :startDate')
            ->andWhere('s.createdAt <= :endDate')
            ->andWhere('s.status = :status')
            ->setParameter('startDate', $startDate)
            ->setParameter('endDate', $endDate)
            ->setParameter('status', Sale::STATUS_COMPLETED)
            ->getQuery()
            ->getSingleScalarResult()
        ;

        return $result ?: '0.00';
    }

    public function getMonthlySalesTotal(\DateTimeInterface $date): string
    {
        $startDate = $date->modify('first day of this month')->setTime(0, 0, 0);
        $endDate = $date->modify('last day of this month')->setTime(23, 59, 59);

        $result = $this->createQueryBuilder('s')
            ->select('SUM(s.total) as total')
            ->where('s.createdAt >= :startDate')
            ->andWhere('s.createdAt <= :endDate')
            ->andWhere('s.status = :status')
            ->setParameter('startDate', $startDate)
            ->setParameter('endDate', $endDate)
            ->setParameter('status', Sale::STATUS_COMPLETED)
            ->getQuery()
            ->getSingleScalarResult()
        ;

        return $result ?: '0.00';
    }

    public function getTopSellingProducts(\DateTimeInterface $startDate, \DateTimeInterface $endDate, int $limit = 5): array
    {
        return $this->createQueryBuilder('s')
            ->select('p.name as productName, SUM(si.quantity) as totalQuantity, SUM(si.totalPrice) as totalRevenue')
            ->innerJoin('s.saleItems', 'si')
            ->innerJoin('si.product', 'p')
            ->where('s.createdAt >= :startDate')
            ->andWhere('s.createdAt <= :endDate')
            ->andWhere('s.status = :status')
            ->setParameter('startDate', $startDate)
            ->setParameter('endDate', $endDate)
            ->setParameter('status', Sale::STATUS_COMPLETED)
            ->groupBy('p.id, p.name')
            ->orderBy('totalQuantity', 'DESC')
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult()
        ;
    }

    public function getSalesByPaymentMethod(\DateTimeInterface $startDate, \DateTimeInterface $endDate): array
    {
        return $this->createQueryBuilder('s')
            ->select('s.paymentMethod, COUNT(s.id) as count, SUM(s.total) as total')
            ->where('s.createdAt >= :startDate')
            ->andWhere('s.createdAt <= :endDate')
            ->andWhere('s.status = :status')
            ->setParameter('startDate', $startDate)
            ->setParameter('endDate', $endDate)
            ->setParameter('status', Sale::STATUS_COMPLETED)
            ->groupBy('s.paymentMethod')
            ->getQuery()
            ->getResult()
        ;
    }
}
