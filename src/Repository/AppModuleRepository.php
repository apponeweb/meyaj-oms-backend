<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\AppModule;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<AppModule>
 */
class AppModuleRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, AppModule::class);
    }

    /** @return AppModule[] */
    public function findAllActive(): array
    {
        return $this->createQueryBuilder('m')
            ->where('m.active = true')
            ->orderBy('m.displayOrder', 'ASC')
            ->getQuery()
            ->getResult();
    }
}
