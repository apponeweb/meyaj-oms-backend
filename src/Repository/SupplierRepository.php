<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\Supplier;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\ORM\QueryBuilder;
use Doctrine\Persistence\ManagerRegistry;

/** @extends ServiceEntityRepository<Supplier> */
class SupplierRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry) { parent::__construct($registry, Supplier::class); }

    public function createPaginatedQueryBuilder(?string $search = null): QueryBuilder
    {
        $qb = $this->createQueryBuilder('s');
        if ($search !== null && $search !== '') {
            $qb->andWhere('s.name LIKE :search OR s.contactName LIKE :search OR s.email LIKE :search')
                ->setParameter('search', '%' . $search . '%');
        }
        return $qb;
    }
}
