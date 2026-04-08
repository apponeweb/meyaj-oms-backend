<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\Paca;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\ORM\QueryBuilder;
use Doctrine\Persistence\ManagerRegistry;

/** @extends ServiceEntityRepository<Paca> */
class PacaRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry) { parent::__construct($registry, Paca::class); }

    public function createPaginatedQueryBuilder(
        ?string $search = null,
        ?int $brandId = null,
        ?int $supplierId = null,
        ?bool $active = null,
        ?int $companyId = null,
        ?int $warehouseId = null,
        ?int $warehouseBinId = null,
    ): QueryBuilder {
        $qb = $this->createQueryBuilder('p')
            ->leftJoin('p.brand', 'br')->addSelect('br')
            ->leftJoin('p.label', 'lb')->addSelect('lb')
            ->leftJoin('p.qualityGrade', 'qg')->addSelect('qg')
            ->leftJoin('p.season', 'sn')->addSelect('sn')
            ->leftJoin('p.gender', 'gn')->addSelect('gn')
            ->leftJoin('p.garmentType', 'gt')->addSelect('gt')
            ->leftJoin('p.fabricType', 'ft')->addSelect('ft')
            ->leftJoin('p.sizeProfile', 'sp')->addSelect('sp')
            ->leftJoin('p.supplier', 'su')->addSelect('su')
            ->leftJoin('App\\Entity\\PacaUnit', 'pu', 'WITH', 'pu.paca = p')
            ->leftJoin('pu.warehouse', 'w')
            ->leftJoin('w.company', 'c')
            ->leftJoin('pu.warehouseBin', 'bin')
            ->groupBy('p.id');

        if ($search !== null && $search !== '') {
            $qb->andWhere('p.name LIKE :search OR p.code LIKE :search')
                ->setParameter('search', '%' . $search . '%');
        }
        if ($brandId !== null) $qb->andWhere('br.id = :brandId')->setParameter('brandId', $brandId);
        if ($supplierId !== null) $qb->andWhere('su.id = :supplierId')->setParameter('supplierId', $supplierId);
        if ($active !== null) $qb->andWhere('p.active = :active')->setParameter('active', $active);
        if ($companyId !== null) {
            $qb->andWhere('c.id = :companyId')->setParameter('companyId', $companyId);
        }
        if ($warehouseId !== null) {
            $qb->andWhere('w.id = :warehouseId')->setParameter('warehouseId', $warehouseId);
        }
        if ($warehouseBinId !== null) {
            $qb->andWhere('bin.id = :warehouseBinId')->setParameter('warehouseBinId', $warehouseBinId);
        }

        return $qb;
    }
}
