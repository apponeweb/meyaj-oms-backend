<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\SaleItem;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<SaleItem>
 */
class SaleItemRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, SaleItem::class);
    }

    public function findBySale(int $saleId): array
    {
        return $this->createQueryBuilder('si')
            ->innerJoin('si.product', 'p')
            ->addSelect('p')
            ->where('si.sale = :saleId')
            ->setParameter('saleId', $saleId)
            ->orderBy('si.id', 'ASC')
            ->getQuery()
            ->getResult()
        ;
    }

    public function findByProduct(int $productId): array
    {
        return $this->createQueryBuilder('si')
            ->innerJoin('si.sale', 's')
            ->addSelect('s')
            ->where('si.product = :productId')
            ->setParameter('productId', $productId)
            ->orderBy('si.createdAt', 'DESC')
            ->getQuery()
            ->getResult()
        ;
    }
}
