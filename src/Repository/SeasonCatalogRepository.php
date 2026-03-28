<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\SeasonCatalog;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\ORM\QueryBuilder;
use Doctrine\Persistence\ManagerRegistry;

/** @extends ServiceEntityRepository<SeasonCatalog> */
class SeasonCatalogRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry) { parent::__construct($registry, SeasonCatalog::class); }

    public function createPaginatedQueryBuilder(?string $search = null): QueryBuilder
    {
        $qb = $this->createQueryBuilder('e');
        if ($search !== null && $search !== '') {
            $qb->andWhere('e.name LIKE :search OR e.description LIKE :search')->setParameter('search', '%' . $search . '%');
        }
        return $qb;
    }
}
