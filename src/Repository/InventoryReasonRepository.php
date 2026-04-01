<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\InventoryReason;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\ORM\QueryBuilder;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<InventoryReason>
 */
class InventoryReasonRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, InventoryReason::class);
    }

    public function createPaginatedQueryBuilder(
        ?string $search = null,
        ?string $direction = null,
        ?string $active = null,
    ): QueryBuilder {
        $qb = $this->createQueryBuilder('r');

        if ($search !== null && $search !== '') {
            $qb->andWhere('r.code LIKE :search OR r.name LIKE :search')
                ->setParameter('search', '%' . $search . '%');
        }

        if ($direction !== null && $direction !== '') {
            $qb->andWhere('r.direction = :direction')
                ->setParameter('direction', $direction);
        }

        if ($active !== null && $active !== '') {
            $qb->andWhere('r.isActive = :active')
                ->setParameter('active', $active === '1');
        }

        return $qb;
    }
}
