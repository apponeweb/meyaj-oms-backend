<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\Branch;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\ORM\QueryBuilder;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Branch>
 */
class BranchRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Branch::class);
    }

    public function createPaginatedQueryBuilder(?string $search = null, ?int $companyId = null): QueryBuilder
    {
        $qb = $this->createQueryBuilder('b')
            ->join('b.company', 'c')
            ->addSelect('c');

        if ($search !== null && $search !== '') {
            $qb->andWhere('b.name LIKE :search OR b.code LIKE :search')
                ->setParameter('search', '%' . $search . '%');
        }

        if ($companyId !== null) {
            $qb->andWhere('c.id = :companyId')
                ->setParameter('companyId', $companyId);
        }

        return $qb;
    }
}
