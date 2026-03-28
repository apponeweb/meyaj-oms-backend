<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\Payment;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Payment>
 */
class PaymentRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Payment::class);
    }

    public function findBySale(int $saleId): array
    {
        return $this->createQueryBuilder('p')
            ->where('p.sale = :saleId')
            ->setParameter('saleId', $saleId)
            ->orderBy('p.createdAt', 'ASC')
            ->getQuery()
            ->getResult()
        ;
    }

    public function findByDateRange(\DateTimeInterface $startDate, \DateTimeInterface $endDate): array
    {
        return $this->createQueryBuilder('p')
            ->innerJoin('p.sale', 's')
            ->addSelect('s')
            ->where('p.createdAt >= :startDate')
            ->andWhere('p.createdAt <= :endDate')
            ->andWhere('p.status = :status')
            ->setParameter('startDate', $startDate)
            ->setParameter('endDate', $endDate)
            ->setParameter('status', Payment::STATUS_COMPLETED)
            ->orderBy('p.createdAt', 'DESC')
            ->getQuery()
            ->getResult()
        ;
    }

    public function getTodayPaymentsTotal(): string
    {
        $today = new \DateTimeImmutable('today');
        $tomorrow = $today->modify('+1 day');

        $result = $this->createQueryBuilder('p')
            ->select('SUM(p.amount) as total')
            ->where('p.createdAt >= :startDate')
            ->andWhere('p.createdAt < :endDate')
            ->andWhere('p.status = :status')
            ->setParameter('startDate', $today)
            ->setParameter('endDate', $tomorrow)
            ->setParameter('status', Payment::STATUS_COMPLETED)
            ->getQuery()
            ->getSingleScalarResult()
        ;

        return $result ?: '0.00';
    }

    public function getPaymentsByMethod(\DateTimeInterface $startDate, \DateTimeInterface $endDate): array
    {
        return $this->createQueryBuilder('p')
            ->select('p.method, COUNT(p.id) as count, SUM(p.amount) as total')
            ->where('p.createdAt >= :startDate')
            ->andWhere('p.createdAt <= :endDate')
            ->andWhere('p.status = :status')
            ->setParameter('startDate', $startDate)
            ->setParameter('endDate', $endDate)
            ->setParameter('status', Payment::STATUS_COMPLETED)
            ->groupBy('p.method')
            ->getQuery()
            ->getResult()
        ;
    }
}
