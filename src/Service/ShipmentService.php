<?php

declare(strict_types=1);

namespace App\Service;

use App\DTO\Request\CreateShipmentOrderRequest;
use App\DTO\Request\ScanShipmentItemRequest;
use App\DTO\Request\ShipShipmentOrderRequest;
use App\DTO\Response\ShipmentOrderDetailResponse;
use App\DTO\Response\ShipmentOrderResponse;
use App\Entity\InventoryReason;
use App\Entity\PacaUnit;
use App\Entity\SalesOrder;
use App\Entity\ShipmentOrder;
use App\Entity\ShipmentOrderItem;
use App\Entity\User;
use App\Entity\Warehouse;
use App\Pagination\PaginatedResponse;
use App\Pagination\PaginationRequest;
use App\Pagination\Paginator;
use App\Repository\PacaUnitRepository;
use App\Repository\ShipmentOrderRepository;
use App\Service\FolioGenerator;
use App\Service\InventoryManager;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

final readonly class ShipmentService
{
    public function __construct(
        private EntityManagerInterface $em,
        private ShipmentOrderRepository $shipmentOrderRepository,
        private PacaUnitRepository $pacaUnitRepository,
        private Paginator $paginator,
        private FolioGenerator $folioGenerator,
        private InventoryManager $inventoryManager,
    ) {
    }

    public function list(PaginationRequest $pagination): PaginatedResponse
    {
        $qb = $this->shipmentOrderRepository->createPaginatedQueryBuilder(
            search: $pagination->search,
            status: $pagination->status,
            warehouseId: $pagination->warehouseId,
            salesOrderId: null,
        );

        $result = $this->paginator->paginate($qb, $pagination);

        return new PaginatedResponse(
            data: array_map(
                static fn (ShipmentOrder $sh) => new ShipmentOrderResponse($sh),
                $result->data,
            ),
            meta: $result->meta,
        );
    }

    public function show(int $id): ShipmentOrderDetailResponse
    {
        $sh = $this->findShipmentOrFail($id);

        return new ShipmentOrderDetailResponse($sh, $this->getUnitsByOrderItems($sh->getSalesOrder()));
    }

    /**
     * @return array<int, array<array{id: int, serial: string, status: string}>>
     */
    private function getUnitsByOrderItems(\App\Entity\SalesOrder $so): array
    {
        $units = $this->em->getRepository(PacaUnit::class)->findBy([
            'salesOrder' => $so,
        ]);

        $map = [];
        foreach ($units as $unit) {
            $itemId = $unit->getSalesOrderItem()?->getId();
            if ($itemId === null) {
                continue;
            }
            $map[$itemId][] = [
                'id' => $unit->getId(),
                'serial' => $unit->getSerial(),
                'status' => $unit->getStatus(),
            ];
        }

        return $map;
    }

    private function findShipmentOrFail(int $id): ShipmentOrder
    {
        $sh = $this->shipmentOrderRepository->createQueryBuilder('sho')
            ->leftJoin('sho.salesOrder', 'so')
            ->leftJoin('so.customer', 'cu')
            ->leftJoin('so.items', 'soi')
            ->leftJoin('soi.paca', 'soip')
            ->leftJoin('sho.warehouse', 'w')
            ->leftJoin('sho.createdBy', 'cb')
            ->leftJoin('sho.items', 'i')
            ->leftJoin('i.pacaUnit', 'pu')
            ->leftJoin('pu.paca', 'p')
            ->leftJoin('i.scannedBy', 'sb')
            ->addSelect('so', 'cu', 'soi', 'soip', 'w', 'cb', 'i', 'pu', 'p', 'sb')
            ->where('sho.id = :id')
            ->setParameter('id', $id)
            ->getQuery()
            ->getOneOrNullResult();

        if (!$sh instanceof ShipmentOrder) {
            throw new NotFoundHttpException(sprintf('Envío con ID %d no encontrado.', $id));
        }

        return $sh;
    }

    public function create(CreateShipmentOrderRequest $request, User $user): ShipmentOrderResponse
    {
        $salesOrder = $this->em->find(SalesOrder::class, $request->salesOrderId);
        if ($salesOrder === null) {
            throw new NotFoundHttpException(sprintf('Pedido de venta con ID %d no encontrado.', $request->salesOrderId));
        }

        $allowedStatuses = [
            SalesOrder::STATUS_RESERVED,
            SalesOrder::STATUS_PREPARING,
            SalesOrder::STATUS_SHIPPED,
        ];
        if (!in_array($salesOrder->getStatus(), $allowedStatuses, true)) {
            throw new BadRequestHttpException(
                sprintf('El pedido de venta debe estar en estado RESERVED, PREPARING o SHIPPED para generar despacho. Estado actual: %s.', $salesOrder->getStatus()),
            );
        }

        if ($salesOrder->getPaymentStatus() !== SalesOrder::PAYMENT_PAID) {
            throw new BadRequestHttpException(
                sprintf('El pedido %s debe estar en estado de pago PAID para proceder a despacho. Estado de pago actual: %s.', $salesOrder->getFolio(), $salesOrder->getPaymentStatus()),
            );
        }

        $warehouse = $this->em->find(Warehouse::class, $request->warehouseId);
        if ($warehouse === null) {
            throw new NotFoundHttpException(sprintf('Almacén con ID %d no encontrado.', $request->warehouseId));
        }

        $existingActiveShipment = $this->shipmentOrderRepository->createQueryBuilder('sho')
            ->where('sho.salesOrder = :salesOrder')
            ->andWhere('sho.status IN (:statuses)')
            ->setParameter('salesOrder', $salesOrder)
            ->setParameter('statuses', [
                ShipmentOrder::STATUS_PENDING,
                ShipmentOrder::STATUS_PICKING,
                ShipmentOrder::STATUS_PACKED,
                ShipmentOrder::STATUS_SHIPPED,
            ])
            ->setMaxResults(1)
            ->getQuery()
            ->getOneOrNullResult();

        if ($existingActiveShipment instanceof ShipmentOrder) {
            throw new ConflictHttpException(
                sprintf('El pedido %s ya tiene un envío activo (%s). Cancélalo o complétalo antes de generar otro.', $salesOrder->getFolio(), $existingActiveShipment->getFolio()),
            );
        }

        $reservedUnits = $this->em->getRepository(PacaUnit::class)->findBy([
            'salesOrder' => $salesOrder,
        ]);
        $this->assertOrderWarehouseConsistency($salesOrder, $warehouse, $reservedUnits);

        $this->em->beginTransaction();

        try {
            $sh = new ShipmentOrder();
            $sh->setSalesOrder($salesOrder);
            $sh->setWarehouse($warehouse);
            $sh->setCreatedBy($user);
            $sh->setCarrier($request->carrier);
            $sh->setNotes($request->notes);

            $folio = $this->folioGenerator->generate('ENV', ShipmentOrder::class);
            $sh->setFolio($folio);

            if ($salesOrder->getStatus() === SalesOrder::STATUS_RESERVED) {
                $fromStatus = $salesOrder->getStatus();
                $salesOrder->setStatus(SalesOrder::STATUS_PREPARING);
                $salesOrder->setDeliveryStatus(SalesOrder::DELIVERY_PREPARING);

                $history = new \App\Entity\SalesOrderStatusHistory();
                $history->setUser($user);
                $history->setFromStatus($fromStatus);
                $history->setToStatus(SalesOrder::STATUS_PREPARING);
                $history->setNotes(sprintf('Pedido enviado a preparación al crear el envío %s', $folio));
                $salesOrder->addStatusHistory($history);
            }

            $this->em->persist($sh);
            $this->em->flush();
            $this->em->commit();

            return new ShipmentOrderResponse($sh);
        } catch (\Exception $e) {
            $this->em->rollback();
            throw $e;
        }
    }

    public function scanItem(int $shipmentId, ScanShipmentItemRequest $request, User $user): ShipmentOrderDetailResponse
    {
        $sh = $this->findShipmentOrFail($shipmentId);

        if (!in_array($sh->getStatus(), [ShipmentOrder::STATUS_PENDING, ShipmentOrder::STATUS_PICKING], true)) {
            throw new BadRequestHttpException(
                sprintf('El envío debe estar en estado PENDING o PICKING para escanear. Estado actual: %s.', $sh->getStatus()),
            );
        }

        $pacaUnit = $this->pacaUnitRepository->findBySerial($request->serial);
        if ($pacaUnit === null) {
            throw new NotFoundHttpException(sprintf('Unidad con serial "%s" no encontrada.', $request->serial));
        }

        // Validate unit belongs to this sales order
        if ($pacaUnit->getSalesOrder() === null || $pacaUnit->getSalesOrder()->getId() !== $sh->getSalesOrder()->getId()) {
            throw new BadRequestHttpException(
                sprintf('La unidad con serial "%s" no pertenece al pedido de venta %s.', $request->serial, $sh->getSalesOrder()->getFolio()),
            );
        }

        if ($pacaUnit->getWarehouse()->getId() !== $sh->getWarehouse()->getId()) {
            throw new ConflictHttpException(
                sprintf('La unidad con serial "%s" pertenece al almacén %s y no puede surtirse en el envío %s del almacén %s.', $request->serial, $pacaUnit->getWarehouse()->getName(), $sh->getFolio(), $sh->getWarehouse()->getName()),
            );
        }

        if ($pacaUnit->getStatus() !== PacaUnit::STATUS_RESERVED) {
            throw new ConflictHttpException(
                sprintf('La unidad con serial "%s" no está en estado RESERVED. Estado actual: %s.', $request->serial, $pacaUnit->getStatus()),
            );
        }

        // Check unit is not already scanned in this shipment
        foreach ($sh->getItems() as $existingItem) {
            if ($existingItem->getPacaUnit()->getId() === $pacaUnit->getId()) {
                throw new ConflictHttpException(
                    sprintf('La unidad con serial "%s" ya fue escaneada en este envío.', $request->serial),
                );
            }
        }

        $this->em->beginTransaction();

        try {
            // Auto-transition from PENDING to PICKING
            if ($sh->getStatus() === ShipmentOrder::STATUS_PENDING) {
                $sh->setStatus(ShipmentOrder::STATUS_PICKING);
            }

            $item = new ShipmentOrderItem();
            $item->setPacaUnit($pacaUnit);
            $item->setScannedBy($user);
            $sh->addItem($item);

            $pacaUnit->setStatus(PacaUnit::STATUS_PICKED);

            $this->em->persist($item);
            $this->em->flush();

            $physicalReason = $this->requireInventoryReason(InventoryReason::CODE_PHYSICAL);
            $this->recordStatusTrace(
                unit: $pacaUnit,
                reason: $physicalReason,
                user: $user,
                referenceType: 'shipment_order',
                referenceId: $sh->getId(),
                notes: sprintf('Unidad %s marcada como PICKED en envío %s', $pacaUnit->getSerial(), $sh->getFolio()),
            );

            $this->em->commit();

            return $this->show($shipmentId);
        } catch (\Exception $e) {
            $this->em->rollback();
            throw $e;
        }
    }

    public function ship(int $id, ShipShipmentOrderRequest $request, User $user): ShipmentOrderDetailResponse
    {
        $sh = $this->findShipmentOrFail($id);

        if (!in_array($sh->getStatus(), [ShipmentOrder::STATUS_PICKING, ShipmentOrder::STATUS_PACKED], true)) {
            throw new BadRequestHttpException(
                sprintf('El envío debe estar en estado PICKING o PACKED para enviar. Estado actual: %s.', $sh->getStatus()),
            );
        }

        if (count($sh->getItems()) === 0) {
            throw new ConflictHttpException(
                sprintf('No se pueden enviar envíos vacíos. Por favor, escanee al menos una unidad antes de enviar el envío %s.', $sh->getFolio()),
            );
        }

        $this->em->beginTransaction();

        try {
            if ($request->trackingNumber !== null) {
                $sh->setTrackingNumber($request->trackingNumber);
            }
            if ($request->carrier !== null) {
                $sh->setCarrier($request->carrier);
            }
            if ($request->notes !== null) {
                $sh->setNotes($request->notes);
            }

            $sh->setStatus(ShipmentOrder::STATUS_SHIPPED);
            $sh->setShippedAt(new \DateTimeImmutable());

            $saleReason = $this->requireInventoryReason(InventoryReason::CODE_SALE);

            // Dispatch all scanned units and group for inventory movements
            $grouped = [];
            foreach ($sh->getItems() as $item) {
                $unit = $item->getPacaUnit();

                if ($unit->getStatus() !== PacaUnit::STATUS_PICKED) {
                    throw new ConflictHttpException(
                        sprintf('La unidad %s no está en estado PICKED y no puede registrar salida física. Estado actual: %s.', $unit->getSerial(), $unit->getStatus()),
                    );
                }

                if ($unit->getWarehouse()->getId() !== $sh->getWarehouse()->getId()) {
                    throw new ConflictHttpException(
                        sprintf('La unidad %s pertenece al almacén %s y no coincide con el almacén %s del envío %s.', $unit->getSerial(), $unit->getWarehouse()->getName(), $sh->getWarehouse()->getName(), $sh->getFolio()),
                    );
                }

                $unit->setStatus(PacaUnit::STATUS_DISPATCHED);

                $key = $unit->getPaca()->getId() . '-' . $unit->getWarehouse()->getId();
                if (!isset($grouped[$key])) {
                    $grouped[$key] = ['paca' => $unit->getPaca(), 'warehouse' => $unit->getWarehouse(), 'count' => 0];
                }
                $grouped[$key]['count']++;
            }

            // Record one OUT movement per paca-warehouse combination
            foreach ($grouped as $group) {
                $this->inventoryManager->recordMovement(
                    paca: $group['paca'],
                    warehouse: $group['warehouse'],
                    bin: null,
                    reason: $saleReason,
                    user: $user,
                    quantity: $group['count'],
                    referenceType: 'shipment_order',
                    referenceId: $sh->getId(),
                    notes: sprintf('Envío %s - %s', $sh->getFolio(), $group['paca']->getCode()),
                );
            }

            // Update sales order status
            $so = $sh->getSalesOrder();
            $fromStatus = $so->getStatus();
            $so->setStatus(SalesOrder::STATUS_SHIPPED);
            $so->setDeliveryStatus(SalesOrder::DELIVERY_SHIPPED);

            $history = new \App\Entity\SalesOrderStatusHistory();
            $history->setUser($user);
            $history->setFromStatus($fromStatus);
            $history->setToStatus(SalesOrder::STATUS_SHIPPED);
            $history->setNotes(sprintf('Pedido despachado mediante el envío %s', $sh->getFolio()));
            $so->addStatusHistory($history);

            // Update cachedStock per affected paca
            $affectedPacas = [];
            foreach ($grouped as $group) {
                $pacaId = $group['paca']->getId();
                if (!isset($affectedPacas[$pacaId])) {
                    $affectedPacas[$pacaId] = $group['paca'];
                }
            }
            foreach ($affectedPacas as $paca) {
                $this->inventoryManager->updateCachedStock($paca);
            }

            $this->em->flush();
            $this->em->commit();

            return $this->show($id);
        } catch (\Exception $e) {
            $this->em->rollback();
            throw $e;
        }
    }

    public function markDelivered(int $id, User $user): ShipmentOrderDetailResponse
    {
        $sh = $this->findShipmentOrFail($id);

        if ($sh->getStatus() !== ShipmentOrder::STATUS_SHIPPED) {
            throw new BadRequestHttpException(
                sprintf('El envío debe estar en estado SHIPPED para marcarse como entregado. Estado actual: %s.', $sh->getStatus()),
            );
        }

        if (count($sh->getItems()) === 0) {
            throw new ConflictHttpException(
                sprintf('El envío %s no tiene unidades escaneadas para marcar entrega.', $sh->getFolio()),
            );
        }

        $this->em->beginTransaction();

        try {
            $sh->setStatus(ShipmentOrder::STATUS_DELIVERED);
            $sh->setDeliveredAt(new \DateTimeImmutable());

            foreach ($sh->getItems() as $item) {
                $unit = $item->getPacaUnit();

                if ($unit->getStatus() !== PacaUnit::STATUS_DISPATCHED) {
                    throw new ConflictHttpException(
                        sprintf('La unidad %s no está en estado DISPATCHED y no puede marcarse como entregada. Estado actual: %s.', $unit->getSerial(), $unit->getStatus()),
                    );
                }

                $unit->setStatus(PacaUnit::STATUS_DELIVERED);

                $physicalReason = $this->requireInventoryReason(InventoryReason::CODE_PHYSICAL);
                $this->recordStatusTrace(
                    unit: $unit,
                    reason: $physicalReason,
                    user: $user,
                    referenceType: 'shipment_order',
                    referenceId: $sh->getId(),
                    notes: sprintf('Unidad %s marcada como DELIVERED en envío %s', $unit->getSerial(), $sh->getFolio()),
                );
            }

            $so = $sh->getSalesOrder();
            $fromStatus = $so->getStatus();
            $so->setStatus(SalesOrder::STATUS_DELIVERED);
            $so->setDeliveryStatus(SalesOrder::DELIVERY_DELIVERED);
            $so->setDeliveredAt(new \DateTimeImmutable());

            $history = new \App\Entity\SalesOrderStatusHistory();
            $history->setUser($user);
            $history->setFromStatus($fromStatus);
            $history->setToStatus(SalesOrder::STATUS_DELIVERED);
            $history->setNotes(sprintf('Pedido entregado al cliente desde el envío %s', $sh->getFolio()));
            $so->addStatusHistory($history);

            $this->em->flush();
            $this->em->commit();

            return $this->show($id);
        } catch (\Exception $e) {
            if ($this->em->getConnection()->isTransactionActive()) {
                $this->em->rollback();
            }
            throw $e;
        }
    }

    private function assertOrderWarehouseConsistency(SalesOrder $salesOrder, Warehouse $warehouse, array $units): void
    {
        if ($salesOrder->getStatus() === SalesOrder::STATUS_RESERVED && count($units) === 0) {
            throw new ConflictHttpException(
                sprintf('El pedido %s está en RESERVED pero no tiene unidades asignadas.', $salesOrder->getFolio()),
            );
        }

        $relevantUnits = array_filter(
            $units,
            static fn (PacaUnit $unit): bool => in_array($unit->getStatus(), [PacaUnit::STATUS_RESERVED, PacaUnit::STATUS_PICKED, PacaUnit::STATUS_DISPATCHED], true),
        );

        if ($relevantUnits === []) {
            return;
        }

        $warehouseIds = [];
        foreach ($relevantUnits as $unit) {
            $warehouseIds[$unit->getWarehouse()->getId()] = $unit->getWarehouse()->getName();
        }

        if (count($warehouseIds) > 1) {
            throw new ConflictHttpException(
                sprintf('El pedido %s tiene unidades asignadas en múltiples almacenes (%s). El flujo actual solo permite surtir desde una sola bodega por envío.', $salesOrder->getFolio(), implode(', ', array_values($warehouseIds))),
            );
        }

        $orderWarehouseId = array_key_first($warehouseIds);
        $orderWarehouseName = (string) current($warehouseIds);
        if ($orderWarehouseId !== $warehouse->getId()) {
            throw new ConflictHttpException(
                sprintf('El pedido %s tiene unidades asignadas en el almacén %s y no coincide con el almacén %s solicitado para el envío.', $salesOrder->getFolio(), $orderWarehouseName, $warehouse->getName()),
            );
        }
    }

    private function recordStatusTrace(
        PacaUnit $unit,
        InventoryReason $reason,
        User $user,
        string $referenceType,
        int $referenceId,
        string $notes,
    ): void {
        $this->inventoryManager->recordMovement(
            paca: $unit->getPaca(),
            warehouse: $unit->getWarehouse(),
            bin: $unit->getWarehouseBin(),
            reason: $reason,
            user: $user,
            quantity: 0,
            referenceType: $referenceType,
            referenceId: $referenceId,
            notes: $notes,
            pacaUnit: $unit,
        );
    }

    private function requireInventoryReason(string $code): InventoryReason
    {
        $reason = $this->em->getRepository(InventoryReason::class)->findOneBy(['code' => $code]);
        if (!$reason instanceof InventoryReason) {
            throw new BadRequestHttpException(sprintf('No está configurado el motivo de inventario %s. Configure el catálogo antes de continuar.', $code));
        }

        return $reason;
    }
}
