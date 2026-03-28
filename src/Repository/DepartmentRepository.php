<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\Department;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\ORM\QueryBuilder;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Department>
 */
class DepartmentRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Department::class);
    }

    public function createPaginatedQueryBuilder(?string $search = null, ?int $branchId = null): QueryBuilder
    {
        $qb = $this->createQueryBuilder('d')
            ->join('d.branch', 'b')
            ->addSelect('b');

        if ($search !== null && $search !== '') {
            $qb->andWhere('d.name LIKE :search OR d.description LIKE :search')
                ->setParameter('search', '%' . $search . '%');
        }

        if ($branchId !== null) {
            $qb->andWhere('b.id = :branchId')
                ->setParameter('branchId', $branchId);
        }

        return $qb;
    }
}
