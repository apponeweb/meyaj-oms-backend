<?php

declare(strict_types=1);

namespace App\Service;

use App\DTO\Response\PacaUnitResponse;
use App\Entity\Paca;
use App\Entity\PacaUnit;
use App\Entity\Warehouse;
use App\Entity\WarehouseBin;
use App\Pagination\PaginatedResponse;
use App\Pagination\PaginationRequest;
use App\Pagination\Paginator;
use App\Repository\PacaUnitRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

final readonly class PacaUnitService
{
    public function __construct(
        private EntityManagerInterface $em,
        private PacaUnitRepository $pacaUnitRepo,
        private Paginator $paginator,
        private InventoryManager $inventoryManager,
        private OperationMode $operationMode,
    ) {}

    /**
     * @param int[]|null $pacaIds
     */
    public function list(
        PaginationRequest $pagination,
        ?int $pacaId = null,
        ?int $warehouseId = null,
        ?int $warehouseBinId = null,
        ?string $status = null,
        ?int $salesOrderId = null,
        ?int $purchaseOrderId = null,
        ?bool $labeled = null,
        ?array $pacaIds = null,
    ): PaginatedResponse {
        $qb = $this->pacaUnitRepo->createPaginatedQueryBuilder(
            $pagination->search,
            $pacaId,
            $warehouseId,
            $warehouseBinId,
            $status,
            $salesOrderId,
            $purchaseOrderId,
            $labeled,
            $pacaIds,
        );
        $result = $this->paginator->paginate($qb, $pagination);

        return new PaginatedResponse(
            data: array_map(
                static fn (PacaUnit $u) => new PacaUnitResponse($u),
                $result->data,
            ),
            meta: $result->meta,
        );
    }

    public function show(int $id): PacaUnitResponse
    {
        $u = $this->pacaUnitRepo->find($id);
        if ($u === null) throw new NotFoundHttpException(\sprintf('Unidad de paca con ID %d no encontrada.', $id));
        return new PacaUnitResponse($u);
    }

    public function findBySerial(string $serial): PacaUnitResponse
    {
        $u = $this->pacaUnitRepo->findBySerial($serial);
        if ($u === null) throw new NotFoundHttpException(\sprintf('Unidad de paca con serial "%s" no encontrada.', $serial));
        return new PacaUnitResponse($u);
    }

    /**
     * @return PacaUnit[]
     */
    public function createBatch(Paca $paca, Warehouse $warehouse, ?WarehouseBin $bin, int $quantity): array
    {
        $units = [];

        for ($i = 0; $i < $quantity; $i++) {
            $serial = $this->generateSerial($paca);

            $unit = new PacaUnit();
            $unit->setPaca($paca);
            $unit->setWarehouse($warehouse);
            $unit->setWarehouseBin($bin);
            $unit->setSerial($serial);
            $unit->setStatus(PacaUnit::STATUS_AVAILABLE);

            $this->em->persist($unit);
            $units[] = $unit;
        }

        $this->em->flush();

        return $units;
    }

    public function move(int $id, Warehouse $warehouse, ?WarehouseBin $bin): PacaUnitResponse
    {
        $u = $this->pacaUnitRepo->find($id);
        if ($u === null) throw new NotFoundHttpException(\sprintf('Unidad de paca con ID %d no encontrada.', $id));

        if (!$u->isAvailable()) {
            throw new BadRequestHttpException(\sprintf(
                'Solo se pueden mover unidades con estatus AVAILABLE. Estatus actual: %s.',
                $u->getStatus(),
            ));
        }

        $u->setWarehouse($warehouse);
        $u->setWarehouseBin($bin);
        $this->em->flush();

        return new PacaUnitResponse($u);
    }

    public function generateSerial(Paca $paca): string
    {
        $count = (int) $this->pacaUnitRepo->createQueryBuilder('pu')
            ->select('COUNT(pu.id)')
            ->where('pu.paca = :paca')
            ->setParameter('paca', $paca)
            ->getQuery()
            ->getSingleScalarResult();

        return \sprintf('%s-%04d', $paca->getCode(), $count + 1);
    }

    /**
     * Mark units as labeled in bulk.
     * @param int[] $ids
     * @return int number of rows updated
     */
    public function markLabeled(array $ids): int
    {
        if (empty($ids)) {
            throw new BadRequestHttpException('Debe proporcionar al menos un ID.');
        }
        return $this->pacaUnitRepo->markLabeledBulk($ids);
    }

    /**
     * Reserve units in bulk (only AVAILABLE units are affected).
     * @param int[] $ids
     * @return array{reserved: int, skipped: int}
     */
    public function reserveBulk(array $ids): array
    {
        if (empty($ids)) {
            throw new BadRequestHttpException('Debe proporcionar al menos un ID.');
        }

        $updated = $this->em->createQuery(
            "UPDATE App\Entity\PacaUnit u
             SET u.status = :reserved
             WHERE u.id IN (:ids) AND u.status = :available"
        )
            ->setParameter('reserved', PacaUnit::STATUS_RESERVED)
            ->setParameter('available', PacaUnit::STATUS_AVAILABLE)
            ->setParameter('ids', $ids)
            ->execute();

        return [
            'reserved' => $updated,
            'skipped'  => \count($ids) - $updated,
        ];
    }

    /**
     * Delete units in bulk. Only AVAILABLE units can be removed.
     * @param array<int, int|string> $identifiers
     * @return array{deleted: int, skipped: int}
     */
    public function deleteBulk(array $identifiers): array
    {
        if (empty($identifiers)) {
            throw new BadRequestHttpException('Debe proporcionar al menos un ID.');
        }

        $numericIds = [];
        $serials = [];

        foreach ($identifiers as $identifier) {
            if (is_int($identifier) || (is_string($identifier) && ctype_digit($identifier))) {
                $value = (int) $identifier;
                if ($value > 0) {
                    $numericIds[] = $value;
                }
                continue;
            }

            if (is_string($identifier) && trim($identifier) !== '') {
                $serials[] = trim($identifier);
            }
        }

        if (empty($numericIds) && empty($serials)) {
            throw new BadRequestHttpException('Los identificadores proporcionados no son válidos.');
        }

        $qb = $this->pacaUnitRepo->createQueryBuilder('u')
            ->leftJoin('u.paca', 'p')->addSelect('p');

        if (!empty($numericIds) && !empty($serials)) {
            $qb->where('u.id IN (:ids) OR u.serial IN (:serials)')
                ->setParameter('ids', array_values(array_unique($numericIds)))
                ->setParameter('serials', array_values(array_unique($serials)));
        } elseif (!empty($numericIds)) {
            $qb->where('u.id IN (:ids)')
                ->setParameter('ids', array_values(array_unique($numericIds)));
        } else {
            $qb->where('u.serial IN (:serials)')
                ->setParameter('serials', array_values(array_unique($serials)));
        }

        $units = $qb->getQuery()->getResult();

        if (empty($units)) {
            throw new NotFoundHttpException('No se encontraron unidades para eliminar.');
        }

        $affectedPacas = [];
        $deleted = 0;
        $skipped = 0;
        $deletableStatuses = $this->operationMode->isInitializing()
            ? [
                PacaUnit::STATUS_AVAILABLE,
                PacaUnit::STATUS_RESERVED,
                PacaUnit::STATUS_SOLD,
                PacaUnit::STATUS_PICKED,
                PacaUnit::STATUS_DISPATCHED,
                PacaUnit::STATUS_RETURNED,
                PacaUnit::STATUS_DAMAGED,
            ]
            : [PacaUnit::STATUS_AVAILABLE];

        $unitsToDelete = [];

        foreach ($units as $unit) {
            if (!$unit instanceof PacaUnit) {
                continue;
            }

            if (!in_array($unit->getStatus(), $deletableStatuses, true)) {
                $skipped++;
                continue;
            }

            $paca = $unit->getPaca();
            $affectedPacas[$paca->getId()] = $paca;
            $unitsToDelete[] = $unit;
        }

        if (!empty($unitsToDelete)) {
            $unitIds = array_map(static fn (PacaUnit $unit) => $unit->getId(), $unitsToDelete);

            $this->em->createQuery('DELETE FROM App\\Entity\\ShipmentOrderItem soi WHERE soi.pacaUnit IN (:unitIds)')
                ->setParameter('unitIds', $unitIds)
                ->execute();

            $this->em->createQuery('DELETE FROM App\\Entity\\InventoryMovement im WHERE im.pacaUnit IN (:unitIds)')
                ->setParameter('unitIds', $unitIds)
                ->execute();

            foreach ($unitsToDelete as $unit) {
                $this->em->remove($unit);
                $deleted++;
            }
        }

        $this->em->flush();

        foreach ($affectedPacas as $paca) {
            $this->inventoryManager->updateCachedStock($paca);
        }

        return [
            'deleted' => $deleted,
            'skipped' => $skipped + max(0, \count($identifiers) - \count($units)),
        ];
    }
}
