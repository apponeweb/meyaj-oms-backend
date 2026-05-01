<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\PacaUnit;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\ORM\QueryBuilder;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<PacaUnit>
 */
class PacaUnitRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, PacaUnit::class);
    }

    /**
     * @param int[]|null $pacaIds
     */
    public function createPaginatedQueryBuilder(
        ?string $search = null,
        ?int $pacaId = null,
        ?int $warehouseId = null,
        ?int $warehouseBinId = null,
        ?string $status = null,
        ?int $salesOrderId = null,
        ?int $purchaseOrderId = null,
        ?bool $labeled = null,
        ?array $pacaIds = null,
    ): QueryBuilder {
        $qb = $this->createQueryBuilder('pu')
            ->leftJoin('pu.paca', 'p')->addSelect('p')
            ->leftJoin('pu.warehouse', 'w')->addSelect('w')
            ->leftJoin('pu.warehouseBin', 'wb')->addSelect('wb');

        if ($search !== null && $search !== '') {
            $qb->andWhere('pu.serial LIKE :search OR p.code LIKE :search OR p.name LIKE :search')
                ->setParameter('search', '%' . $search . '%');
        }
        if ($pacaIds !== null && count($pacaIds) > 0) {
            $qb->andWhere('p.id IN (:pacaIds)')->setParameter('pacaIds', $pacaIds);
        } elseif ($pacaId !== null) {
            $qb->andWhere('p.id = :pacaId')->setParameter('pacaId', $pacaId);
        }
        if ($warehouseId !== null) {
            $qb->andWhere('w.id = :warehouseId')->setParameter('warehouseId', $warehouseId);
        }
        if ($warehouseBinId !== null) {
            $qb->andWhere('wb.id = :warehouseBinId')->setParameter('warehouseBinId', $warehouseBinId);
        }
        if ($status !== null && $status !== '') {
            $qb->andWhere('pu.status = :status')->setParameter('status', $status);
        }
        if ($salesOrderId !== null) {
            $qb->andWhere('pu.salesOrder = :salesOrderId')->setParameter('salesOrderId', $salesOrderId);
        }
        if ($purchaseOrderId !== null) {
            $qb->andWhere('pu.purchaseOrder = :purchaseOrderId')->setParameter('purchaseOrderId', $purchaseOrderId);
        }
        if ($labeled === true) {
            $qb->andWhere('pu.labeledAt IS NOT NULL');
        } elseif ($labeled === false) {
            $qb->andWhere('pu.labeledAt IS NULL');
        }

        return $qb;
    }

    /**
     * Mark multiple units as labeled (bulk).
     * @param int[] $ids
     */
    public function markLabeledBulk(array $ids): int
    {
        if (empty($ids)) {
            return 0;
        }

        return (int) $this->createQueryBuilder('pu')
            ->update()
            ->set('pu.labeledAt', ':now')
            ->set('pu.updatedAt', ':now')
            ->where('pu.id IN (:ids)')
            ->setParameter('now', new \DateTimeImmutable())
            ->setParameter('ids', $ids)
            ->getQuery()
            ->execute();
    }


    public function countAvailableByPaca(int $pacaId): int
    {
        return (int) $this->createQueryBuilder('pu')
            ->select('COUNT(pu.id)')
            ->where('pu.paca = :pacaId')
            ->andWhere('pu.status = :status')
            ->setParameter('pacaId', $pacaId)
            ->setParameter('status', PacaUnit::STATUS_AVAILABLE)
            ->getQuery()
            ->getSingleScalarResult();
    }

    public function countTrackedByPaca(int $pacaId): int
    {
        return (int) $this->createQueryBuilder('pu')
            ->select('COUNT(pu.id)')
            ->where('pu.paca = :pacaId')
            ->andWhere('pu.status IN (:statuses)')
            ->setParameter('pacaId', $pacaId)
            ->setParameter('statuses', [
                PacaUnit::STATUS_AVAILABLE,
                PacaUnit::STATUS_RESERVED,
                PacaUnit::STATUS_PICKED,
            ])
            ->getQuery()
            ->getSingleScalarResult();
    }

    /**
     * @param int[] $pacaIds
     * @return array<int, int> Map of pacaId => availableCount
     */
    public function countAvailableByPacaIds(array $pacaIds): array
    {
        if (empty($pacaIds)) {
            return [];
        }

        $rows = $this->createQueryBuilder('pu')
            ->select('IDENTITY(pu.paca) AS pacaId, COUNT(pu.id) AS cnt')
            ->where('pu.paca IN (:ids)')
            ->andWhere('pu.status = :status')
            ->setParameter('ids', $pacaIds)
            ->setParameter('status', PacaUnit::STATUS_AVAILABLE)
            ->groupBy('pu.paca')
            ->getQuery()
            ->getArrayResult();

        $map = [];
        foreach ($rows as $row) {
            $map[(int) $row['pacaId']] = (int) $row['cnt'];
        }

        return $map;
    }

    /**
     * @param int[] $pacaIds
     * @return array<int, int> Map of pacaId => trackedCount across all warehouses
     */
    public function countTrackedByPacaIds(array $pacaIds): array
    {
        if (empty($pacaIds)) {
            return [];
        }

        $rows = $this->createQueryBuilder('pu')
            ->select('IDENTITY(pu.paca) AS pacaId, COUNT(pu.id) AS cnt')
            ->where('pu.paca IN (:ids)')
            ->andWhere('pu.status IN (:statuses)')
            ->setParameter('ids', $pacaIds)
            ->setParameter('statuses', [
                PacaUnit::STATUS_AVAILABLE,
                PacaUnit::STATUS_RESERVED,
                PacaUnit::STATUS_PICKED,
            ])
            ->groupBy('pu.paca')
            ->getQuery()
            ->getArrayResult();

        $map = [];
        foreach ($rows as $row) {
            $map[(int) $row['pacaId']] = (int) $row['cnt'];
        }

        return $map;
    }

    /**
     * @param int[] $pacaIds
     * @return array<int, int> Map of pacaId => availableCount respecting optional warehouse filters
     */
    public function countAvailableByPacaIdsFiltered(array $pacaIds, ?int $warehouseId = null, ?int $warehouseBinId = null): array
    {
        if (empty($pacaIds)) {
            return [];
        }

        $qb = $this->createQueryBuilder('pu')
            ->select('IDENTITY(pu.paca) AS pacaId, COUNT(pu.id) AS cnt')
            ->where('pu.paca IN (:ids)')
            ->andWhere('pu.status = :status')
            ->setParameter('ids', $pacaIds)
            ->setParameter('status', PacaUnit::STATUS_AVAILABLE);

        if ($warehouseId !== null) {
            $qb->andWhere('IDENTITY(pu.warehouse) = :warehouseId')
                ->setParameter('warehouseId', $warehouseId);
        }

        if ($warehouseBinId !== null) {
            $qb->andWhere('IDENTITY(pu.warehouseBin) = :warehouseBinId')
                ->setParameter('warehouseBinId', $warehouseBinId);
        }

        $rows = $qb
            ->groupBy('pu.paca')
            ->getQuery()
            ->getArrayResult();

        $map = [];
        foreach ($rows as $row) {
            $map[(int) $row['pacaId']] = (int) $row['cnt'];
        }

        return $map;
    }

    /**
     * @param int[] $pacaIds
     * @return array<int, int> Map of pacaId => trackedCount respecting optional warehouse filters
     */
    public function countTrackedByPacaIdsFiltered(array $pacaIds, ?int $warehouseId = null, ?int $warehouseBinId = null): array
    {
        if (empty($pacaIds)) {
            return [];
        }

        $qb = $this->createQueryBuilder('pu')
            ->select('IDENTITY(pu.paca) AS pacaId, COUNT(pu.id) AS cnt')
            ->where('pu.paca IN (:ids)')
            ->andWhere('pu.status IN (:statuses)')
            ->setParameter('ids', $pacaIds)
            ->setParameter('statuses', [
                PacaUnit::STATUS_AVAILABLE,
                PacaUnit::STATUS_RESERVED,
                PacaUnit::STATUS_PICKED,
            ]);

        if ($warehouseId !== null) {
            $qb->andWhere('IDENTITY(pu.warehouse) = :warehouseId')
                ->setParameter('warehouseId', $warehouseId);
        }

        if ($warehouseBinId !== null) {
            $qb->andWhere('IDENTITY(pu.warehouseBin) = :warehouseBinId')
                ->setParameter('warehouseBinId', $warehouseBinId);
        }

        $rows = $qb
            ->groupBy('pu.paca')
            ->getQuery()
            ->getArrayResult();

        $map = [];
        foreach ($rows as $row) {
            $map[(int) $row['pacaId']] = (int) $row['cnt'];
        }

        return $map;
    }

    /**
     * @return array<array{warehouseId: int, warehouseName: string, available: int, reserved: int, total: int}>
     */
    public function countByPacaAndWarehouse(int $pacaId): array
    {
        $rows = $this->createQueryBuilder('pu')
            ->select(
                'IDENTITY(pu.warehouse) AS warehouseId',
                'w.name AS warehouseName',
                'SUM(CASE WHEN pu.status = :available THEN 1 ELSE 0 END) AS available',
                'SUM(CASE WHEN pu.status = :reserved THEN 1 ELSE 0 END) AS reserved',
                'COUNT(pu.id) AS total',
            )
            ->leftJoin('pu.warehouse', 'w')
            ->where('pu.paca = :pacaId')
            ->andWhere('pu.status NOT IN (:excluded)')
            ->setParameter('pacaId', $pacaId)
            ->setParameter('available', PacaUnit::STATUS_AVAILABLE)
            ->setParameter('reserved', PacaUnit::STATUS_RESERVED)
            ->setParameter('excluded', [PacaUnit::STATUS_SOLD, PacaUnit::STATUS_DAMAGED])
            ->groupBy('pu.warehouse, w.name')
            ->getQuery()
            ->getArrayResult();

        return array_map(static fn (array $r) => [
            'warehouseId' => (int) $r['warehouseId'],
            'warehouseName' => $r['warehouseName'],
            'available' => (int) $r['available'],
            'reserved' => (int) $r['reserved'],
            'total' => (int) $r['total'],
        ], $rows);
    }

    /**
     * @return PacaUnit[]
     */
    public function findAvailableByPaca(int $pacaId, int $limit): array
    {
        return $this->createQueryBuilder('pu')
            ->where('pu.paca = :pacaId')
            ->andWhere('pu.status = :status')
            ->setParameter('pacaId', $pacaId)
            ->setParameter('status', PacaUnit::STATUS_AVAILABLE)
            ->orderBy('pu.id', 'ASC')
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();
    }

    public function findBySerial(string $serial): ?PacaUnit
    {
        return $this->findOneBy(['serial' => $serial]);
    }

    public function getNextSerialNumber(int $pacaId): int
    {
        $max = $this->createQueryBuilder('pu')
            ->select('MAX(pu.id)')
            ->where('pu.paca = :pacaId')
            ->setParameter('pacaId', $pacaId)
            ->getQuery()
            ->getSingleScalarResult();

        return $max !== null ? ((int) $max + 1) : 1;
    }

    public function countByPacaAndWarehouseForCount(int $pacaId, int $warehouseId): int
    {
        return (int) $this->createQueryBuilder('pu')
            ->select('COUNT(pu.id)')
            ->where('pu.paca = :pacaId')
            ->andWhere('pu.warehouse = :warehouseId')
            ->andWhere('pu.status IN (:statuses)')
            ->setParameter('pacaId', $pacaId)
            ->setParameter('warehouseId', $warehouseId)
            ->setParameter('statuses', [PacaUnit::STATUS_AVAILABLE, PacaUnit::STATUS_RESERVED])
            ->getQuery()
            ->getSingleScalarResult();
    }

    /**
     * @return PacaUnit[]
     */
    public function findAvailableInWarehouse(int $pacaId, int $warehouseId, int $limit): array
    {
        return $this->createQueryBuilder('pu')
            ->where('pu.paca = :pacaId')
            ->andWhere('pu.warehouse = :warehouseId')
            ->andWhere('pu.status = :status')
            ->setParameter('pacaId', $pacaId)
            ->setParameter('warehouseId', $warehouseId)
            ->setParameter('status', PacaUnit::STATUS_AVAILABLE)
            ->orderBy('pu.id', 'ASC')
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();
    }
}
