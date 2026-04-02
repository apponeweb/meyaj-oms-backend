<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\PacaLocation;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<PacaLocation>
 */
class PacaLocationRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, PacaLocation::class);
    }
}
