<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\Category;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\ORM\QueryBuilder;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Category>
 */
class CategoryRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Category::class);
    }

    public function createPaginatedQueryBuilder(?string $search = null): QueryBuilder
    {
        $qb = $this->createQueryBuilder('c')
            ->select('c');

        if ($search !== null && $search !== '') {
            $qb->andWhere('c.name LIKE :search OR c.description LIKE :search')
                ->setParameter('search', '%' . $search . '%');
        }

        return $qb;
    }
}
