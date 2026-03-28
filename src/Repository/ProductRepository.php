<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\Product;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\ORM\QueryBuilder;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Product>
 */
class ProductRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Product::class);
    }

    public function createPaginatedQueryBuilder(
        ?string $search = null,
        ?int $categoryId = null,
        ?bool $active = null,
        ?string $minPrice = null,
        ?string $maxPrice = null,
    ): QueryBuilder {
        $qb = $this->createQueryBuilder('p')
            ->select('p')
            ->leftJoin('p.category', 'c')
            ->addSelect('c');

        if ($search !== null && $search !== '') {
            $qb->andWhere('p.name LIKE :search OR p.description LIKE :search')
                ->setParameter('search', '%' . $search . '%');
        }

        if ($categoryId !== null) {
            $qb->andWhere('p.category = :categoryId')
                ->setParameter('categoryId', $categoryId);
        }

        if ($active !== null) {
            $qb->andWhere('p.active = :active')
                ->setParameter('active', $active);
        }

        if ($minPrice !== null) {
            $qb->andWhere('p.price >= :minPrice')
                ->setParameter('minPrice', $minPrice);
        }

        if ($maxPrice !== null) {
            $qb->andWhere('p.price <= :maxPrice')
                ->setParameter('maxPrice', $maxPrice);
        }

        return $qb;
    }
}
