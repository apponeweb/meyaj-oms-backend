<?php

declare(strict_types=1);

namespace App\Service;

use App\DTO\Request\BulkUpdatePacaRequest;
use App\DTO\Request\CreatePacaRequest;
use App\DTO\Request\UpdatePacaRequest;
use App\DTO\Response\PacaResponse;
use App\Entity\InventoryMovement;
use App\Entity\InventoryReason;
use App\Entity\Paca;
use App\Entity\PacaUnit;
use App\Entity\User;
use App\Entity\Warehouse;
use App\Entity\WarehouseBin;
use App\Pagination\PaginatedResponse;
use App\Pagination\PaginationRequest;
use App\Pagination\Paginator;
use App\Repository\PacaRepository;
use App\Repository\PacaUnitRepository;
use App\Repository\RoleActionPermissionRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

final readonly class PacaService
{
    public function __construct(
        private EntityManagerInterface $em,
        private PacaRepository $pacaRepository,
        private PacaUnitRepository $pacaUnitRepo,
        private RoleActionPermissionRepository $roleActionPermissionRepository,
        private Paginator $paginator,
        private InventoryManager $inventoryManager,
        private OperationMode $operationMode,
    ) {}

    public function list(
        PaginationRequest $pagination,
        ?int $brandId = null,
        ?int $supplierId = null,
        ?bool $active = null,
        ?int $companyId = null,
        ?int $warehouseId = null,
        ?int $warehouseBinId = null
    ): PaginatedResponse {
        $qb = $this->pacaRepository->createPaginatedQueryBuilder(
            $pagination->search,
            $brandId,
            $supplierId,
            $active,
            $companyId,
            $warehouseId,
            $warehouseBinId
        );
        $result = $this->paginator->paginate($qb, $pagination);

        $pacaIds = array_map(static fn (Paca $p) => $p->getId(), $result->data);
        $hasLocationFilter = $warehouseId !== null || $warehouseBinId !== null;
        $availableStock = $hasLocationFilter
            ? $this->pacaUnitRepo->countAvailableByPacaIdsFiltered($pacaIds, $warehouseId, $warehouseBinId)
            : $this->pacaUnitRepo->countAvailableByPacaIds($pacaIds);
        $reservedStock = $hasLocationFilter
            ? $this->pacaUnitRepo->countReservedByPacaIdsFiltered($pacaIds, $warehouseId, $warehouseBinId)
            : $this->pacaUnitRepo->countReservedByPacaIds($pacaIds);
        $trackedStock = $hasLocationFilter
            ? $this->pacaUnitRepo->countTrackedByPacaIdsFiltered($pacaIds, $warehouseId, $warehouseBinId)
            : $this->pacaUnitRepo->countTrackedByPacaIds($pacaIds);

        return new PaginatedResponse(
            data: array_map(static fn (Paca $p) => new PacaResponse(
                $p,
                $availableStock[$p->getId()] ?? 0,
                null,
                $trackedStock[$p->getId()] ?? 0,
                $reservedStock[$p->getId()] ?? 0,
            ), $result->data),
            meta: $result->meta,
        );
    }

    public function show(int $id): PacaResponse
    {
        $p = $this->pacaRepository->find($id);
        if ($p === null) throw new NotFoundHttpException(\sprintf('Paca con ID %d no encontrada.', $id));
        $availableStock = $this->pacaUnitRepo->countAvailableByPaca($p->getId());
        $reservedStock = $this->pacaUnitRepo->countReservedByPaca($p->getId());
        $stockByWarehouse = $this->pacaUnitRepo->countByPacaAndWarehouse($p->getId());
        $trackedStock = $this->pacaUnitRepo->countTrackedByPaca($p->getId());
        return new PacaResponse($p, $availableStock, $stockByWarehouse, $trackedStock, $reservedStock);
    }

    public function getNextCode(): array
    {
        $maxResult = $this->em->createQueryBuilder()
            ->select('MAX(p.id)')
            ->from(Paca::class, 'p')
            ->getQuery()
            ->getSingleScalarResult();
        $next = ($maxResult !== null ? (int) $maxResult : 0) + 1;

        return ['code' => sprintf('PAC-%04d', $next)];
    }

    public function create(CreatePacaRequest $r): PacaResponse
    {
        $p = new Paca();

        // Auto-generate code if not provided or empty
        $code = $r->code;
        if ($code === '' || $code === 'AUTO') {
            $maxResult = $this->em->createQueryBuilder()
                ->select('MAX(p.id)')
                ->from(Paca::class, 'p')
                ->getQuery()
                ->getSingleScalarResult();
            $next = ($maxResult !== null ? (int) $maxResult : 0) + 1;
            $code = sprintf('PAC-%04d', $next);
        }

        $p->setCode($code);
        $p->setName($r->name);
        $p->setDescription($r->description);
        $p->setPurchasePrice($r->purchasePrice);
        $p->setSellingPrice($r->sellingPrice);
        $p->setCachedStock(0);
        $p->setPieceCount($r->pieceCount);
        $p->setWeight($r->weight);
        $this->setRelations($p, $r->brandId, $r->labelId, $r->qualityGradeId, $r->seasonId, $r->genderId, $r->garmentTypeId, $r->fabricTypeId, $r->sizeProfileId, $r->supplierId);
        $this->em->persist($p);
        $this->em->flush();
        return new PacaResponse($p);
    }

    public function update(int $id, UpdatePacaRequest $r): PacaResponse
    {
        $p = $this->pacaRepository->find($id);
        if ($p === null) throw new NotFoundHttpException(sprintf('Paca con ID %d no encontrada.', $id));
        if ($r->code !== null) $p->setCode($r->code);
        if ($r->name !== null) $p->setName($r->name);
        if ($r->description !== null) $p->setDescription($r->description);
        if ($r->purchasePrice !== null) $p->setPurchasePrice($r->purchasePrice);
        if ($r->sellingPrice !== null) $p->setSellingPrice($r->sellingPrice);
        if ($r->pieceCount !== null) $p->setPieceCount($r->pieceCount);
        if ($r->weight !== null) $p->setWeight($r->weight);
        if ($r->active !== null) $p->setActive($r->active);
        $this->setRelations($p, $r->brandId, $r->labelId, $r->qualityGradeId, $r->seasonId, $r->genderId, $r->garmentTypeId, $r->fabricTypeId, $r->sizeProfileId, $r->supplierId);
        $this->em->flush();
        return new PacaResponse($p);
    }

    public function bulkUpdate(BulkUpdatePacaRequest $r, ?User $user): array
    {
        if ($user === null || $user->getRole() === null) {
            throw new AccessDeniedHttpException('No tiene permisos para actualizar pacas masivamente.');
        }

        $hasPermission = $this->roleActionPermissionRepository->roleHasAllowedActionByCodes(
            $user->getRole()->getId(),
            'pacas',
            'update',
        );

        if (!$hasPermission) {
            throw new AccessDeniedHttpException('No tiene permisos para actualizar pacas masivamente.');
        }

        $pacaIds = array_values(array_unique(array_map('intval', $r->pacaIds)));
        if ($pacaIds === []) {
            throw new BadRequestHttpException('Debe proporcionar al menos una paca para actualizar.');
        }

        $pacas = $this->pacaRepository->findBy(['id' => $pacaIds]);
        if ($pacas === []) {
            throw new NotFoundHttpException('No se encontraron pacas para actualizar.');
        }

        foreach ($pacas as $paca) {
            if ($r->purchasePrice !== null) {
                $paca->setPurchasePrice($r->purchasePrice);
            }

            if ($r->sellingPrice !== null) {
                $paca->setSellingPrice($r->sellingPrice);
            }

            $this->setRelations(
                $paca,
                $r->brandId,
                $r->labelId,
                $r->qualityGradeId,
                $r->seasonId,
                $r->genderId,
                $r->garmentTypeId,
                $r->fabricTypeId,
                $r->sizeProfileId,
                $r->supplierId,
            );
        }

        $this->em->flush();

        return [
            'requested' => count($pacaIds),
            'updated' => count($pacas),
        ];
    }

    public function delete(int $id): void
    {
        $p = $this->pacaRepository->find($id);
        if ($p === null) throw new NotFoundHttpException(sprintf('Paca con ID %d no encontrada.', $id));
        if ($this->operationMode->isInitializing()) {
            $this->deleteForInitialization($p);
            return;
        }
        $unitsCount = $this->pacaUnitRepo->count(['paca' => $p]);
        if ($unitsCount > 0) {
            throw new BadRequestHttpException(sprintf(
                'No se puede eliminar la paca %s porque tiene %d unidad(es) asociada(s). Elimine primero sus unidades de inventario.',
                $p->getCode(),
                $unitsCount,
            ));
        }
        $this->em->remove($p);
        $this->em->flush();
    }

    public function addStock(int $pacaId, int $warehouseId, ?int $warehouseBinId, int $quantity, ?User $user): PacaResponse
    {
        $paca = $this->em->find(Paca::class, $pacaId);
        if ($paca === null) {
            throw new NotFoundHttpException(sprintf('Paca con ID %d no encontrada.', $pacaId));
        }

        $warehouse = $this->em->find(Warehouse::class, $warehouseId);
        if ($warehouse === null) {
            throw new NotFoundHttpException(sprintf('Almacen con ID %d no encontrado.', $warehouseId));
        }

        $warehouseBin = null;
        if ($warehouseBinId !== null) {
            $warehouseBin = $this->em->find(WarehouseBin::class, $warehouseBinId);
            if ($warehouseBin === null) {
                throw new NotFoundHttpException(sprintf('Ubicación con ID %d no encontrada.', $warehouseBinId));
            }

            if ($warehouseBin->getWarehouse()->getId() !== $warehouse->getId()) {
                throw new BadRequestHttpException(sprintf(
                    'La ubicación %s no pertenece al almacén %s.',
                    $warehouseBin->getName(),
                    $warehouse->getName(),
                ));
            }
        }

        // Create PacaUnits
        $existingCount = $this->em->getRepository(PacaUnit::class)->count(['paca' => $paca]);
        for ($i = 0; $i < $quantity; $i++) {
            $unit = new PacaUnit();
            $unit->setSerial(sprintf('%s-%04d', $paca->getCode(), $existingCount + $i + 1));
            $unit->setPaca($paca);
            $unit->setWarehouse($warehouse);
            if ($warehouseBin !== null) {
                $unit->setWarehouseBin($warehouseBin);
            }
            $this->em->persist($unit);
        }

        $this->em->flush();

        $this->inventoryManager->updateCachedStock($paca);

        // Record inventory movement
        if ($user === null) {
            throw new BadRequestHttpException('No se pudo identificar el usuario para registrar el movimiento de inventario.');
        }

        $reason = $this->resolveAdjustmentInReason();
        $this->recordAddStockMovement(
            paca: $paca,
            warehouse: $warehouse,
            warehouseBin: $warehouseBin,
            reason: $reason,
            user: $user,
            quantity: $quantity,
            notes: sprintf('Carga de stock manual: %d unidades en %s', $quantity, $warehouse->getName()),
        );

        $available = $this->pacaUnitRepo->countAvailableByPaca($paca->getId());
        $stockByWarehouse = $this->pacaUnitRepo->countByPacaAndWarehouse($paca->getId());
        $trackedStock = $this->pacaUnitRepo->countTrackedByPaca($paca->getId());
        return new PacaResponse($paca, $available, $stockByWarehouse, $trackedStock);
    }

    private function recordAddStockMovement(
        Paca $paca,
        Warehouse $warehouse,
        ?WarehouseBin $warehouseBin,
        InventoryReason $reason,
        User $user,
        int $quantity,
        string $notes,
    ): void {
        $movement = new InventoryMovement();
        $movement->setCompany($warehouse->getCompany());
        $movement->setPaca($paca);
        $movement->setWarehouse($warehouse);
        $movement->setWarehouseBin($warehouseBin);
        $movement->setReason($reason);
        $movement->setUser($user);
        $movement->setMovementType($reason->getDirection());
        $movement->setReferenceType('manual_stock');
        $movement->setReferenceId(null);
        $movement->setQtyIn($quantity);
        $movement->setQtyOut(0);
        $movement->setBalanceAfter($this->countTrackedUnitsInWarehouse($paca, $warehouse));
        $movement->setUnitCost($paca->getPurchasePrice());
        $movement->setNotes($notes);

        $this->em->persist($movement);
        $this->em->flush();
    }

    private function countTrackedUnitsInWarehouse(Paca $paca, Warehouse $warehouse): int
    {
        return (int) $this->em->createQueryBuilder()
            ->select('COUNT(u.id)')
            ->from(PacaUnit::class, 'u')
            ->where('u.paca = :paca')
            ->andWhere('u.warehouse = :warehouse')
            ->andWhere('u.status IN (:statuses)')
            ->setParameter('paca', $paca)
            ->setParameter('warehouse', $warehouse)
            ->setParameter('statuses', [
                PacaUnit::STATUS_AVAILABLE,
                PacaUnit::STATUS_RESERVED,
                PacaUnit::STATUS_PICKED,
            ])
            ->getQuery()
            ->getSingleScalarResult();
    }

    private function resolveAdjustmentInReason(): InventoryReason
    {
        $reason = $this->em->getRepository(InventoryReason::class)->findOneBy([
            'code' => InventoryReason::CODE_ADJ_IN,
        ]);

        if ($reason instanceof InventoryReason) {
            return $reason;
        }

        $reason = new InventoryReason();
        $reason->setCode(InventoryReason::CODE_ADJ_IN);
        $reason->setName('Ajuste Entrada');
        $reason->setDirection('IN');
        $reason->setRequiresReference(false);
        $reason->setIsActive(true);

        $this->em->persist($reason);
        $this->em->flush();

        return $reason;
    }

    private function deleteForInitialization(Paca $paca): void
    {
        $unitIds = array_map(
            static fn (PacaUnit $unit) => $unit->getId(),
            $this->em->createQuery('SELECT pu FROM App\\Entity\\PacaUnit pu WHERE pu.paca = :paca')
                ->setParameter('paca', $paca)
                ->getResult(),
        );

        $salesOrderIds = array_map(
            static fn (array $row) => (int) $row['id'],
            $this->em->createQuery(
                'SELECT DISTINCT so.id AS id
                 FROM App\\Entity\\SalesOrder so
                 INNER JOIN so.items soi
                 WHERE soi.paca = :paca'
            )
                ->setParameter('paca', $paca)
                ->getArrayResult(),
        );

        if (!empty($unitIds)) {
            $this->em->createQuery('DELETE FROM App\\Entity\\ShipmentOrderItem soi WHERE soi.pacaUnit IN (:unitIds)')
                ->setParameter('unitIds', $unitIds)
                ->execute();

            $this->em->createQuery('DELETE FROM App\\Entity\\InventoryMovement im WHERE im.pacaUnit IN (:unitIds)')
                ->setParameter('unitIds', $unitIds)
                ->execute();

            $this->em->createQuery('DELETE FROM App\\Entity\\PacaUnit pu WHERE pu.id IN (:unitIds)')
                ->setParameter('unitIds', $unitIds)
                ->execute();
        }

        if (!empty($salesOrderIds)) {
            $shipmentIds = array_map(
                static fn (array $row) => (int) $row['id'],
                $this->em->createQuery('SELECT sh.id AS id FROM App\\Entity\\ShipmentOrder sh WHERE sh.salesOrder IN (:salesOrderIds)')
                    ->setParameter('salesOrderIds', $salesOrderIds)
                    ->getArrayResult(),
            );

            if (!empty($shipmentIds)) {
                $this->em->createQuery('DELETE FROM App\\Entity\\ShipmentOrderItem soi WHERE soi.shipmentOrder IN (:shipmentIds)')
                    ->setParameter('shipmentIds', $shipmentIds)
                    ->execute();

                $this->em->createQuery("DELETE FROM App\\Entity\\InventoryMovement im WHERE im.referenceType = :shipmentType AND im.referenceId IN (:shipmentIds)")
                    ->setParameter('shipmentType', 'shipment_order')
                    ->setParameter('shipmentIds', $shipmentIds)
                    ->execute();

                $this->em->createQuery('DELETE FROM App\\Entity\\ShipmentOrder sh WHERE sh.id IN (:shipmentIds)')
                    ->setParameter('shipmentIds', $shipmentIds)
                    ->execute();
            }

            $this->em->createQuery("DELETE FROM App\\Entity\\InventoryMovement im WHERE im.referenceType = :salesType AND im.referenceId IN (:salesOrderIds)")
                ->setParameter('salesType', 'sales_order')
                ->setParameter('salesOrderIds', $salesOrderIds)
                ->execute();

            $this->em->createQuery('DELETE FROM App\\Entity\\SalesOrderStatusHistory sosh WHERE sosh.salesOrder IN (:salesOrderIds)')
                ->setParameter('salesOrderIds', $salesOrderIds)
                ->execute();

            $this->em->createQuery('DELETE FROM App\\Entity\\SalesOrderItem soi WHERE soi.salesOrder IN (:salesOrderIds)')
                ->setParameter('salesOrderIds', $salesOrderIds)
                ->execute();

            $this->em->createQuery('DELETE FROM App\\Entity\\SalesOrder so WHERE so.id IN (:salesOrderIds)')
                ->setParameter('salesOrderIds', $salesOrderIds)
                ->execute();
        }

        $this->em->remove($paca);
        $this->em->flush();
    }

    private function setRelations(Paca $p, ?int $brandId, ?int $labelId, ?int $qualityGradeId, ?int $seasonId, ?int $genderId, ?int $garmentTypeId, ?int $fabricTypeId, ?int $sizeProfileId, ?int $supplierId): void
    {
        if ($brandId !== null) $p->setBrand($this->em->getRepository(\App\Entity\Brand::class)->find($brandId));
        if ($labelId !== null) $p->setLabel($this->em->getRepository(\App\Entity\LabelCatalog::class)->find($labelId));
        if ($qualityGradeId !== null) $p->setQualityGrade($this->em->getRepository(\App\Entity\QualityGrade::class)->find($qualityGradeId));
        if ($seasonId !== null) $p->setSeason($this->em->getRepository(\App\Entity\SeasonCatalog::class)->find($seasonId));
        if ($genderId !== null) $p->setGender($this->em->getRepository(\App\Entity\GenderCatalog::class)->find($genderId));
        if ($garmentTypeId !== null) $p->setGarmentType($this->em->getRepository(\App\Entity\GarmentType::class)->find($garmentTypeId));
        if ($fabricTypeId !== null) $p->setFabricType($this->em->getRepository(\App\Entity\FabricType::class)->find($fabricTypeId));
        if ($sizeProfileId !== null) $p->setSizeProfile($this->em->getRepository(\App\Entity\SizeProfile::class)->find($sizeProfileId));
        if ($supplierId !== null) $p->setSupplier($this->em->getRepository(\App\Entity\Supplier::class)->find($supplierId));
    }
}
