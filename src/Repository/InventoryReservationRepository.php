<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\InventoryReservation;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\ORM\QueryBuilder;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<InventoryReservation>
 */
class InventoryReservationRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, InventoryReservation::class);
    }

    public function createPaginatedQueryBuilder(
        ?int $pacaId = null,
        ?string $status = null,
    ): QueryBuilder {
        $qb = $this->createQueryBuilder('r')
            ->leftJoin('r.paca', 'p')
            ->addSelect('p')
            ->leftJoin('r.user', 'u')
            ->addSelect('u');

        if ($pacaId !== null) {
            $qb->andWhere('p.id = :pacaId')
                ->setParameter('pacaId', $pacaId);
        }

        if ($status !== null && $status !== '') {
            $qb->andWhere('r.status = :status')
                ->setParameter('status', $status);
        }

        return $qb;
    }

    /**
     * @return InventoryReservation[]
     */
    public function findActiveByPaca(int $pacaId): array
    {
        return $this->createQueryBuilder('r')
            ->andWhere('r.paca = :pacaId')
            ->andWhere('r.status = :status')
            ->setParameter('pacaId', $pacaId)
            ->setParameter('status', 'ACTIVE')
            ->getQuery()
            ->getResult();
    }

    /**
     * @param int[] $pacaIds
     * @return array<int, int> Map of pacaId => reservedQuantity
     */
    public function getActiveReservedQuantityByPacaIds(array $pacaIds): array
    {
        if (empty($pacaIds)) {
            return [];
        }

        $results = $this->createQueryBuilder('r')
            ->select('IDENTITY(r.paca) as pacaId, COALESCE(SUM(r.quantity), 0) as reserved')
            ->andWhere('r.paca IN (:ids)')
            ->andWhere('r.status = :status')
            ->setParameter('ids', $pacaIds)
            ->setParameter('status', 'ACTIVE')
            ->groupBy('r.paca')
            ->getQuery()
            ->getArrayResult();

        $map = [];
        foreach ($results as $row) {
            $map[(int) $row['pacaId']] = (int) $row['reserved'];
        }

        return $map;
    }

    public function getActiveReservedQuantity(int $pacaId): int
    {
        $result = $this->createQueryBuilder('r')
            ->select('COALESCE(SUM(r.quantity), 0)')
            ->andWhere('r.paca = :pacaId')
            ->andWhere('r.status = :status')
            ->setParameter('pacaId', $pacaId)
            ->setParameter('status', 'ACTIVE')
            ->getQuery()
            ->getSingleScalarResult();

        return (int) $result;
    }
}
