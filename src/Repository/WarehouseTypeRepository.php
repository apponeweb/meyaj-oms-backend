<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\WarehouseType;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\ORM\QueryBuilder;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<WarehouseType>
 */
class WarehouseTypeRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, WarehouseType::class);
    }

    public function createPaginatedQueryBuilder(
        ?string $search = null,
        ?string $active = null,
    ): QueryBuilder {
        $qb = $this->createQueryBuilder('wt');

        if ($search !== null && $search !== '') {
            $qb->andWhere('wt.name LIKE :search OR wt.description LIKE :search')
                ->setParameter('search', '%' . $search . '%');
        }

        if ($active !== null && $active !== '') {
            $qb->andWhere('wt.isActive = :active')
                ->setParameter('active', $active === '1');
        }

        return $qb;
    }
}
