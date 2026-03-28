<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\SupplierBrand;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/** @extends ServiceEntityRepository<SupplierBrand> */
class SupplierBrandRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry) { parent::__construct($registry, SupplierBrand::class); }
}
