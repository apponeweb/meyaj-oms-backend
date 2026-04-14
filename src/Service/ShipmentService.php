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

        if ($sh === null) {
            throw new NotFoundHttpException(sprintf('Envío con ID %d no encontrado.', $id));
        }

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

    public function create(CreateShipmentOrderRequest $request, User $user): ShipmentOrderResponse
    {
        $salesOrder = $this->em->find(SalesOrder::class, $request->salesOrderId);
        if ($salesOrder === null) {
            throw new NotFoundHttpException(sprintf('Pedido de venta con ID %d no encontrado.', $request->salesOrderId));
        }

        $allowedStatuses = [
            SalesOrder::STATUS_CONFIRMED,
            SalesOrder::STATUS_PREPARING,
            SalesOrder::STATUS_SHIPPED,
        ];
        if (!in_array($salesOrder->getStatus(), $allowedStatuses, true)) {
            throw new BadRequestHttpException(
                sprintf('El pedido de venta debe estar en estado CONFIRMADO, PREPARANDO o ENVIADO. Estado actual: %s.', $salesOrder->getStatus()),
            );
        }

        $warehouse = $this->em->find(Warehouse::class, $request->warehouseId);
        if ($warehouse === null) {
            throw new NotFoundHttpException(sprintf('Almacén con ID %d no encontrado.', $request->warehouseId));
        }

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

            if ($salesOrder->getStatus() === SalesOrder::STATUS_CONFIRMED) {
                $salesOrder->setStatus(SalesOrder::STATUS_PREPARING);
                $salesOrder->setDeliveryStatus(SalesOrder::DELIVERY_PREPARING);
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

            $saleReason = $this->em->getRepository(InventoryReason::class)
                ->findOneBy(['code' => InventoryReason::CODE_SALE]);

            // Dispatch all scanned units and group for inventory movements
            $grouped = [];
            foreach ($sh->getItems() as $item) {
                $unit = $item->getPacaUnit();
                $unit->setStatus(PacaUnit::STATUS_DISPATCHED);

                $key = $unit->getPaca()->getId() . '-' . $unit->getWarehouse()->getId();
                if (!isset($grouped[$key])) {
                    $grouped[$key] = ['paca' => $unit->getPaca(), 'warehouse' => $unit->getWarehouse(), 'count' => 0];
                }
                $grouped[$key]['count']++;
            }

            // Record one OUT movement per paca-warehouse combination
            if ($saleReason !== null) {
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
            }

            // Update sales order status
            $so = $sh->getSalesOrder();
            $so->setStatus(SalesOrder::STATUS_SHIPPED);
            $so->setDeliveryStatus(SalesOrder::DELIVERY_SHIPPED);

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
                sprintf('El envío debe estar en estado SHIPPED para marcar como entregado. Estado actual: %s.', $sh->getStatus()),
            );
        }

        $this->em->beginTransaction();

        try {
            $sh->setStatus(ShipmentOrder::STATUS_DELIVERED);
            $sh->setDeliveredAt(new \DateTimeImmutable());

            // Mark all units as SOLD
            foreach ($sh->getItems() as $item) {
                $item->getPacaUnit()->setStatus(PacaUnit::STATUS_SOLD);
            }

            // Update sales order
            $so = $sh->getSalesOrder();
            $so->setStatus(SalesOrder::STATUS_DELIVERED);
            $so->setDeliveredAt(new \DateTimeImmutable());
            $so->setDeliveryStatus(SalesOrder::DELIVERY_DELIVERED);

            $this->em->flush();
            $this->em->commit();

            return $this->show($id);
        } catch (\Exception $e) {
            $this->em->rollback();
            throw $e;
        }
    }

    public function cancel(int $id, User $user): ShipmentOrderDetailResponse
    {
        $sh = $this->findShipmentOrFail($id);

        if ($sh->getStatus() === ShipmentOrder::STATUS_DELIVERED || $sh->getStatus() === ShipmentOrder::STATUS_CANCELLED) {
            throw new BadRequestHttpException(
                sprintf('No se puede cancelar un envío en estado %s.', $sh->getStatus()),
            );
        }

        $this->em->beginTransaction();

        try {
            $currentStatus = $sh->getStatus();

            if (in_array($currentStatus, [ShipmentOrder::STATUS_PICKING, ShipmentOrder::STATUS_PACKED], true)) {
                // Revert scanned units back to RESERVED
                foreach ($sh->getItems() as $item) {
                    $item->getPacaUnit()->setStatus(PacaUnit::STATUS_RESERVED);
                }
            }

            if ($currentStatus === ShipmentOrder::STATUS_SHIPPED) {
                $returnReason = $this->em->getRepository(InventoryReason::class)
                    ->findOneBy(['code' => InventoryReason::CODE_RETURN]);

                $grouped = [];
                foreach ($sh->getItems() as $item) {
                    $unit = $item->getPacaUnit();
                    $unit->setStatus(PacaUnit::STATUS_RETURNED);

                    $key = $unit->getPaca()->getId() . '-' . $unit->getWarehouse()->getId();
                    if (!isset($grouped[$key])) {
                        $grouped[$key] = ['paca' => $unit->getPaca(), 'warehouse' => $unit->getWarehouse(), 'count' => 0];
                    }
                    $grouped[$key]['count']++;
                }

                if ($returnReason !== null) {
                    foreach ($grouped as $group) {
                        $this->inventoryManager->recordMovement(
                            paca: $group['paca'],
                            warehouse: $group['warehouse'],
                            bin: null,
                            reason: $returnReason,
                            user: $user,
                            quantity: $group['count'],
                            referenceType: 'shipment_order',
                            referenceId: $sh->getId(),
                            notes: sprintf('Cancelación envío %s - %s', $sh->getFolio(), $group['paca']->getCode()),
                            forceAdjustment: true,
                        );
                    }
                }

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
            }

            $sh->setStatus(ShipmentOrder::STATUS_CANCELLED);

            $this->em->flush();
            $this->em->commit();

            return $this->show($id);
        } catch (\Exception $e) {
            $this->em->rollback();
            throw $e;
        }
    }

    private function findShipmentOrFail(int $id): ShipmentOrder
    {
        $sh = $this->shipmentOrderRepository->find($id);
        if ($sh === null) {
            throw new NotFoundHttpException(sprintf('Envío con ID %d no encontrado.', $id));
        }

        return $sh;
    }
}
