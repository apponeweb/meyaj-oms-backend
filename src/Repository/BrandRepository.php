<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\Brand;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\ORM\QueryBuilder;
use Doctrine\Persistence\ManagerRegistry;

/** @extends ServiceEntityRepository<Brand> */
class BrandRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry) { parent::__construct($registry, Brand::class); }

    public function createPaginatedQueryBuilder(?string $search = null): QueryBuilder
    {
        $qb = $this->createQueryBuilder('b');
        if ($search !== null && $search !== '') {
            $qb->andWhere('b.name LIKE :search OR b.description LIKE :search')
                ->setParameter('search', '%' . $search . '%');
        }
        return $qb;
    }
}
