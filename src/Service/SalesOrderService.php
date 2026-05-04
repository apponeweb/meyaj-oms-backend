<?php

declare(strict_types=1);

namespace App\Service;

use App\DTO\Request\ChangeStatusRequest;
use App\DTO\Request\CreateSalesOrderRequest;
use App\DTO\Request\PartialReturnSalesOrderRequest;
use App\DTO\Request\ReturnSalesOrderRequest;
use App\DTO\Response\SalesOrderDetailResponse;
use App\DTO\Response\SalesOrderResponse;
use App\Entity\Branch;
use App\Entity\Company;
use App\Entity\Customer;
use App\Entity\InventoryReason;
use App\Entity\Paca;
use App\Entity\PacaUnit;
use App\Entity\SalesOrder;
use App\Entity\SalesOrderItem;
use App\Entity\SalesOrderStatusHistory;
use App\Entity\User;
use App\Pagination\PaginatedResponse;
use App\Pagination\PaginationRequest;
use App\Pagination\Paginator;
use App\Repository\SalesOrderRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

final readonly class SalesOrderService
{
    private const ALLOWED_TRANSITIONS = [
        SalesOrder::STATUS_PENDING => [SalesOrder::STATUS_CONFIRMED, SalesOrder::STATUS_CANCELLED],
        SalesOrder::STATUS_CONFIRMED => [SalesOrder::STATUS_RESERVED, SalesOrder::STATUS_PREPARING, SalesOrder::STATUS_CANCELLED],
        SalesOrder::STATUS_RESERVED => [SalesOrder::STATUS_PREPARING, SalesOrder::STATUS_CANCELLED],
        SalesOrder::STATUS_PREPARING => [SalesOrder::STATUS_SHIPPED, SalesOrder::STATUS_CANCELLED],
        SalesOrder::STATUS_SHIPPED => [SalesOrder::STATUS_DELIVERED],
        SalesOrder::STATUS_DELIVERED => [],
        SalesOrder::STATUS_CANCELLED => [],
        SalesOrder::STATUS_RETURNED => [],
    ];

    public function __construct(
        private EntityManagerInterface $em,
        private SalesOrderRepository $salesOrderRepository,
        private Paginator $paginator,
        private FolioGenerator $folioGenerator,
        private InventoryManager $inventoryManager,
    ) {
    }

    public function list(PaginationRequest $pagination): PaginatedResponse
    {
        $qb = $this->salesOrderRepository->createPaginatedQueryBuilder(
            search: $pagination->search,
            customerId: $pagination->customerId,
            status: $pagination->status,
            channel: $pagination->channel,
            paymentStatus: $pagination->paymentStatus,
            companyId: $pagination->companyId,
        );

        $result = $this->paginator->paginate($qb, $pagination);

        return new PaginatedResponse(
            data: array_map(
                static fn (SalesOrder $so) => new SalesOrderResponse($so),
                $result->data,
            ),
            meta: $result->meta,
        );
    }

    public function show(int $id): SalesOrderDetailResponse
    {
        $so = $this->salesOrderRepository->createQueryBuilder('so')
            ->leftJoin('so.company', 'c')
            ->leftJoin('so.customer', 'cu')
            ->leftJoin('so.user', 'u')
            ->leftJoin('so.seller', 'se')
            ->leftJoin('so.items', 'i')
            ->leftJoin('i.paca', 'p')
            ->leftJoin('so.statusHistory', 'sh')
            ->leftJoin('sh.user', 'shu')
            ->addSelect('c', 'cu', 'u', 'se', 'i', 'p', 'sh', 'shu')
            ->where('so.id = :id')
            ->setParameter('id', $id)
            ->getQuery()
            ->getOneOrNullResult();

        if ($so === null) {
            throw new NotFoundHttpException(sprintf('Pedido de venta con ID %d no encontrado.', $id));
        }

        // Load assigned units for each item
        $unitsByItem = $this->getUnitsByOrderItems($so);

        return new SalesOrderDetailResponse($so, $unitsByItem);
    }

    public function create(CreateSalesOrderRequest $request, User $currentUser): SalesOrderResponse
    {
        $this->em->beginTransaction();

        try {
            $company = $this->em->find(Company::class, $request->companyId);
            if ($company === null) {
                throw new NotFoundHttpException(sprintf('Empresa con ID %d no encontrada.', $request->companyId));
            }

            $customer = $this->em->find(Customer::class, $request->customerId);
            if ($customer === null) {
                throw new NotFoundHttpException(sprintf('Cliente con ID %d no encontrado.', $request->customerId));
            }

            $so = new SalesOrder();
            $so->setCompany($company);
            $so->setCustomer($customer);
            $so->setUser($currentUser);
            $so->setChannel($request->channel);
            $so->setOrderType($request->orderType);
            $so->setCustomerAddress($request->customerAddress);
            $so->setNotes($request->notes);
            $so->setSourceWhatsapp($request->sourceWhatsapp);

            if ($request->branchId !== null) {
                $branch = $this->em->find(Branch::class, $request->branchId);
                if ($branch === null) {
                    throw new NotFoundHttpException(sprintf('Sucursal con ID %d no encontrada.', $request->branchId));
                }
                $so->setBranch($branch);
            }

            if ($request->sellerId !== null) {
                $seller = $this->em->find(User::class, $request->sellerId);
                if ($seller === null) {
                    throw new NotFoundHttpException(sprintf('Vendedor con ID %d no encontrado.', $request->sellerId));
                }
                $so->setSeller($seller);
            }

            if ($request->estimatedDelivery !== null) {
                $so->setEstimatedDelivery(new \DateTime($request->estimatedDelivery));
            }

            $folio = $this->folioGenerator->generateWithDate('PV', SalesOrder::class);
            $so->setFolio($folio);

            // Persist SO first to get ID for PacaUnit FK
            $this->em->persist($so);
            $this->em->flush();

            $subtotal = '0.00';
            $totalDiscount = '0.00';

            foreach ($request->items as $itemData) {
                $paca = $this->em->find(Paca::class, $itemData['pacaId']);
                if ($paca === null) {
                    throw new NotFoundHttpException(sprintf('Paca con ID %d no encontrada.', $itemData['pacaId']));
                }

                $quantity = (int) $itemData['quantity'];
                $unitPrice = (string) $itemData['unitPrice'];
                $itemDiscount = isset($itemData['discount']) ? (string) $itemData['discount'] : '0.00';

                $lineTotal = bcsub(
                    bcmul($unitPrice, (string) $quantity, 2),
                    $itemDiscount,
                    2,
                );

                $item = new SalesOrderItem();
                $item->setPaca($paca);
                $item->setQuantity($quantity);
                $item->setUnitPrice($unitPrice);
                $item->setDiscount($itemDiscount);
                $item->setTotalPrice($lineTotal);
                $item->setNotes($itemData['notes'] ?? null);

                $so->addItem($item);
                $this->em->persist($item);
                $this->em->flush(); // Flush to get item ID

                $subtotal = bcadd($subtotal, bcmul($unitPrice, (string) $quantity, 2), 2);
                $totalDiscount = bcadd($totalDiscount, $itemDiscount, 2);
            }

            $total = bcsub($subtotal, $totalDiscount, 2);

            $so->setSubtotal($subtotal);
            $so->setDiscount($totalDiscount);
            $so->setTotal($total);

            // Initial status history
            $history = new SalesOrderStatusHistory();
            $history->setUser($currentUser);
            $history->setFromStatus(null);
            $history->setToStatus(SalesOrder::STATUS_PENDING);
            $history->setNotes('Pedido creado');
            $so->addStatusHistory($history);

            $this->em->flush();
            $this->em->commit();

            return new SalesOrderResponse($so);
        } catch (\Exception $e) {
            $this->em->rollback();
            throw $e;
        }
    }

    public function reserve(int $id, User $currentUser): SalesOrderDetailResponse
    {
        $so = $this->salesOrderRepository->createQueryBuilder('so')
            ->leftJoin('so.company', 'c')
            ->leftJoin('so.customer', 'cu')
            ->leftJoin('so.user', 'u')
            ->leftJoin('so.seller', 'se')
            ->leftJoin('so.items', 'i')
            ->leftJoin('i.paca', 'p')
            ->leftJoin('so.statusHistory', 'sh')
            ->leftJoin('sh.user', 'shu')
            ->addSelect('c', 'cu', 'u', 'se', 'i', 'p', 'sh', 'shu')
            ->where('so.id = :id')
            ->setParameter('id', $id)
            ->getQuery()
            ->getOneOrNullResult();

        if ($so === null) {
            throw new NotFoundHttpException(sprintf('Pedido de venta con ID %d no encontrado.', $id));
        }

        if (!in_array($so->getStatus(), [SalesOrder::STATUS_PENDING, SalesOrder::STATUS_CONFIRMED], true)) {
            throw new BadRequestHttpException(
                sprintf('Solo se puede reservar inventario para pedidos en estado PENDING o CONFIRMED. Estado actual: %s.', $so->getStatus()),
            );
        }

        $existingUnits = $this->em->getRepository(PacaUnit::class)->count([
            'salesOrder' => $so,
        ]);

        if ($existingUnits > 0) {
            throw new BadRequestHttpException('El pedido ya tiene unidades asignadas. No se puede reservar nuevamente.');
        }

        $this->em->beginTransaction();

        try {
            $fromStatus = $so->getStatus();

            foreach ($so->getItems() as $item) {
                if ($item->getPaca() === null) {
                    throw new ConflictHttpException(
                        sprintf('La línea %d del pedido %s no tiene una paca válida asociada para reservar inventario.', $item->getId(), $so->getFolio()),
                    );
                }

                $this->inventoryManager->reserveStock(
                    $item->getPaca(),
                    $so,
                    $item,
                    $item->getQuantity(),
                );
            }

            $so->setStatus(SalesOrder::STATUS_RESERVED);

            $history = new SalesOrderStatusHistory();
            $history->setUser($currentUser);
            $history->setFromStatus($fromStatus);
            $history->setToStatus(SalesOrder::STATUS_RESERVED);
            $history->setNotes('Inventario reservado explícitamente');
            $so->addStatusHistory($history);

            $this->em->flush();
            $this->em->commit();

            $unitsByItem = $this->getUnitsByOrderItems($so);

            return new SalesOrderDetailResponse($so, $unitsByItem);
        } catch (ConflictHttpException|BadRequestHttpException|NotFoundHttpException $e) {
            if ($this->em->getConnection()->isTransactionActive()) {
                $this->em->rollback();
            }
            throw $e;
        } catch (\Throwable $e) {
            if ($this->em->getConnection()->isTransactionActive()) {
                $this->em->rollback();
            }
            throw new ConflictHttpException(
                sprintf('No se pudo reservar inventario para el pedido %s. Verifique stock disponible por unidades y consistencia de las pacas asociadas.', $so->getFolio()),
                previous: $e,
            );
        }
    }

    public function changeStatus(int $id, ChangeStatusRequest $request, User $currentUser): SalesOrderDetailResponse
    {
        $so = $this->salesOrderRepository->createQueryBuilder('so')
            ->leftJoin('so.company', 'c')
            ->leftJoin('so.customer', 'cu')
            ->leftJoin('so.user', 'u')
            ->leftJoin('so.seller', 'se')
            ->leftJoin('so.items', 'i')
            ->leftJoin('i.paca', 'p')
            ->leftJoin('so.statusHistory', 'sh')
            ->leftJoin('sh.user', 'shu')
            ->addSelect('c', 'cu', 'u', 'se', 'i', 'p', 'sh', 'shu')
            ->where('so.id = :id')
            ->setParameter('id', $id)
            ->getQuery()
            ->getOneOrNullResult();

        if ($so === null) {
            throw new NotFoundHttpException(sprintf('Pedido de venta con ID %d no encontrado.', $id));
        }

        $currentStatus = $so->getStatus();
        $newStatus = $request->status;

        $allowed = self::ALLOWED_TRANSITIONS[$currentStatus] ?? [];
        if (!in_array($newStatus, $allowed, true)) {
            throw new BadRequestHttpException(
                sprintf('No se puede cambiar de estado "%s" a "%s".', $currentStatus, $newStatus),
            );
        }

        if ($newStatus === SalesOrder::STATUS_RESERVED) {
            throw new BadRequestHttpException(
                'El estado "RESERVED" solo se puede asignar desde la operación explícita de reserva del pedido.',
            );
        }

        if ($newStatus === SalesOrder::STATUS_SHIPPED) {
            throw new BadRequestHttpException(
                'El estado "ENVIADO" solo se puede asignar desde el modulo de Despacho. Crea una Orden de Envio y marca como enviado desde ahi.',
            );
        }

        if ($newStatus === SalesOrder::STATUS_DELIVERED) {
            throw new BadRequestHttpException(
                'El estado "ENTREGADO" solo se puede asignar desde el modulo de Despacho. Marca la Orden de Envio como entregada.',
            );
        }

        if ($newStatus === SalesOrder::STATUS_RETURNED || $newStatus === SalesOrder::STATUS_PARTIALLY_RETURNED) {
            throw new BadRequestHttpException(
                'Los estados de devolución solo se pueden asignar desde las operaciones explícitas de devolución del pedido.',
            );
        }

        $this->em->beginTransaction();

        try {
            $so->setStatus($newStatus);

            $history = new SalesOrderStatusHistory();
            $history->setUser($currentUser);
            $history->setFromStatus($currentStatus);
            $history->setToStatus($newStatus);
            $history->setNotes($request->notes);
            $so->addStatusHistory($history);

            if ($newStatus === SalesOrder::STATUS_CANCELLED) {
                $this->inventoryManager->releaseAllUnitsForOrder($so);
            }

            $this->em->flush();
            $this->em->commit();

            $unitsByItem = $this->getUnitsByOrderItems($so);

            return new SalesOrderDetailResponse($so, $unitsByItem);
        } catch (\Exception $e) {
            $this->em->rollback();
            throw $e;
        }
    }

    public function returnOrder(int $id, ReturnSalesOrderRequest $request, User $currentUser): SalesOrderDetailResponse
    {
        $so = $this->salesOrderRepository->createQueryBuilder('so')
            ->leftJoin('so.company', 'c')
            ->leftJoin('so.customer', 'cu')
            ->leftJoin('so.user', 'u')
            ->leftJoin('so.seller', 'se')
            ->leftJoin('so.items', 'i')
            ->leftJoin('i.paca', 'p')
            ->leftJoin('so.statusHistory', 'sh')
            ->leftJoin('sh.user', 'shu')
            ->addSelect('c', 'cu', 'u', 'se', 'i', 'p', 'sh', 'shu')
            ->where('so.id = :id')
            ->setParameter('id', $id)
            ->getQuery()
            ->getOneOrNullResult();

        if ($so === null) {
            throw new NotFoundHttpException(sprintf('Pedido de venta con ID %d no encontrado.', $id));
        }

        if (!in_array($so->getStatus(), [SalesOrder::STATUS_SHIPPED, SalesOrder::STATUS_DELIVERED, SalesOrder::STATUS_PARTIALLY_RETURNED], true)) {
            throw new BadRequestHttpException(
                sprintf('Solo se puede devolver un pedido en estado SHIPPED, DELIVERED o PARTIALLY_RETURNED. Estado actual: %s.', $so->getStatus()),
            );
        }

        $units = $this->em->getRepository(PacaUnit::class)->findBy([
            'salesOrder' => $so,
        ]);

        $returnableUnits = array_filter(
            $units,
            static fn (PacaUnit $unit): bool => in_array($unit->getStatus(), [PacaUnit::STATUS_DISPATCHED, PacaUnit::STATUS_SOLD], true),
        );

        if ($returnableUnits === []) {
            throw new BadRequestHttpException(
                sprintf('El pedido %s no tiene unidades en estado despachado o vendido para registrar devolución.', $so->getFolio()),
            );
        }

        $this->em->beginTransaction();

        try {
            $this->applyReturnToUnits(
                so: $so,
                units: array_values($returnableUnits),
                currentUser: $currentUser,
                targetStatus: SalesOrder::STATUS_RETURNED,
                historyNotes: $request->notes ?? 'Devolución explícita del pedido',
                movementNotesTemplate: 'Devolución explícita pedido %s - %s',
                unitTraceTemplate: 'Unidad %s marcada como RETURNED en devolución explícita del pedido %s',
            );

            $unitsByItem = $this->getUnitsByOrderItems($so);

            return new SalesOrderDetailResponse($so, $unitsByItem);
        } catch (\Exception $e) {
            $this->em->rollback();
            throw $e;
        }
    }

    public function partialReturnOrder(int $id, PartialReturnSalesOrderRequest $request, User $currentUser): SalesOrderDetailResponse
    {
        $so = $this->salesOrderRepository->createQueryBuilder('so')
            ->leftJoin('so.company', 'c')
            ->leftJoin('so.customer', 'cu')
            ->leftJoin('so.user', 'u')
            ->leftJoin('so.seller', 'se')
            ->leftJoin('so.items', 'i')
            ->leftJoin('i.paca', 'p')
            ->leftJoin('so.statusHistory', 'sh')
            ->leftJoin('sh.user', 'shu')
            ->addSelect('c', 'cu', 'u', 'se', 'i', 'p', 'sh', 'shu')
            ->where('so.id = :id')
            ->setParameter('id', $id)
            ->getQuery()
            ->getOneOrNullResult();

        if ($so === null) {
            throw new NotFoundHttpException(sprintf('Pedido de venta con ID %d no encontrado.', $id));
        }

        if (!in_array($so->getStatus(), [SalesOrder::STATUS_SHIPPED, SalesOrder::STATUS_DELIVERED, SalesOrder::STATUS_PARTIALLY_RETURNED], true)) {
            throw new BadRequestHttpException(
                sprintf('Solo se puede registrar devolución parcial para pedidos en estado SHIPPED, DELIVERED o PARTIALLY_RETURNED. Estado actual: %s.', $so->getStatus()),
            );
        }

        $units = $this->em->getRepository(PacaUnit::class)->findBy([
            'salesOrder' => $so,
        ]);

        $unitsById = [];
        $returnableUnits = [];
        foreach ($units as $unit) {
            $unitsById[$unit->getId()] = $unit;
            if (in_array($unit->getStatus(), [PacaUnit::STATUS_DISPATCHED, PacaUnit::STATUS_SOLD], true)) {
                $returnableUnits[$unit->getId()] = $unit;
            }
        }

        $selectedUnits = [];
        foreach (array_values(array_unique($request->unitIds)) as $unitId) {
            if (!isset($unitsById[$unitId])) {
                throw new BadRequestHttpException(sprintf('La unidad %d no pertenece al pedido %s.', $unitId, $so->getFolio()));
            }
            if (!isset($returnableUnits[$unitId])) {
                throw new BadRequestHttpException(sprintf('La unidad %s no está en estado retornable para el pedido %s.', $unitsById[$unitId]->getSerial(), $so->getFolio()));
            }

            $selectedUnits[] = $unitsById[$unitId];
        }

        if ($selectedUnits === []) {
            throw new BadRequestHttpException('Debe seleccionar al menos una unidad para devolución parcial.');
        }

        $remainingReturnableCount = count($returnableUnits) - count($selectedUnits);
        $targetStatus = $remainingReturnableCount === 0 ? SalesOrder::STATUS_RETURNED : SalesOrder::STATUS_PARTIALLY_RETURNED;

        $this->em->beginTransaction();

        try {
            $this->applyReturnToUnits(
                so: $so,
                units: $selectedUnits,
                currentUser: $currentUser,
                targetStatus: $targetStatus,
                historyNotes: $request->notes ?? 'Devolución parcial explícita del pedido',
                movementNotesTemplate: 'Devolución parcial pedido %s - %s',
                unitTraceTemplate: 'Unidad %s marcada como RETURNED en devolución parcial del pedido %s',
            );

            $unitsByItem = $this->getUnitsByOrderItems($so);

            return new SalesOrderDetailResponse($so, $unitsByItem);
        } catch (\Exception $e) {
            $this->em->rollback();
            throw $e;
        }
    }

    public function changePaymentStatus(int $id, ChangeStatusRequest $request, User $currentUser): SalesOrderDetailResponse
    {
        $so = $this->salesOrderRepository->createQueryBuilder('so')
            ->leftJoin('so.company', 'c')
            ->leftJoin('so.customer', 'cu')
            ->leftJoin('so.user', 'u')
            ->leftJoin('so.seller', 'se')
            ->leftJoin('so.items', 'i')
            ->leftJoin('i.paca', 'p')
            ->leftJoin('so.statusHistory', 'sh')
            ->leftJoin('sh.user', 'shu')
            ->addSelect('c', 'cu', 'u', 'se', 'i', 'p', 'sh', 'shu')
            ->where('so.id = :id')
            ->setParameter('id', $id)
            ->getQuery()
            ->getOneOrNullResult();

        if ($so === null) {
            throw new NotFoundHttpException(sprintf('Pedido de venta con ID %d no encontrado.', $id));
        }

        $newPaymentStatus = $request->status;
        if (!in_array($newPaymentStatus, [
            SalesOrder::PAYMENT_PENDING,
            SalesOrder::PAYMENT_PARTIAL,
            SalesOrder::PAYMENT_PAID,
            SalesOrder::PAYMENT_REFUNDED,
        ], true)) {
            throw new BadRequestHttpException(sprintf('Estado de pago "%s" no válido.', $newPaymentStatus));
        }

        if ($newPaymentStatus === SalesOrder::PAYMENT_REFUNDED) {
            throw new BadRequestHttpException(
                'El estado de pago "REFUNDED" solo se asigna automáticamente desde devoluciones explícitas.',
            );
        }

        $this->em->beginTransaction();

        try {
            $currentPaymentStatus = $so->getPaymentStatus();

            $so->setPaymentStatus($newPaymentStatus);

            $history = new SalesOrderStatusHistory();
            $history->setUser($currentUser);
            $history->setFromStatus(sprintf('PAGO:%s', $currentPaymentStatus));
            $history->setToStatus(sprintf('PAGO:%s', $newPaymentStatus));
            $history->setNotes($request->notes ?? sprintf('Estado de pago actualizado a %s', $newPaymentStatus));
            $so->addStatusHistory($history);

            $this->em->flush();
            $this->em->commit();

            $unitsByItem = $this->getUnitsByOrderItems($so);

            return new SalesOrderDetailResponse($so, $unitsByItem);
        } catch (\Exception $e) {
            $this->em->rollback();
            throw $e;
        }
    }

    /**
     * @param list<PacaUnit> $units
     */
    private function applyReturnToUnits(
        SalesOrder $so,
        array $units,
        User $currentUser,
        string $targetStatus,
        string $historyNotes,
        string $movementNotesTemplate,
        string $unitTraceTemplate,
    ): void {
        $fromStatus = $so->getStatus();
        $returnReason = $this->requireInventoryReason(InventoryReason::CODE_RETURN);
        $physicalReason = $this->requireInventoryReason(InventoryReason::CODE_PHYSICAL);

        $so->setStatus($targetStatus);
        $so->setDeliveryStatus(SalesOrder::DELIVERY_RETURNED);
        $this->reconcilePaymentStatusAfterReturn($so, $targetStatus);

        $history = new SalesOrderStatusHistory();
        $history->setUser($currentUser);
        $history->setFromStatus($fromStatus);
        $history->setToStatus($targetStatus);
        $history->setNotes($historyNotes);
        $so->addStatusHistory($history);

        $grouped = [];
        foreach ($units as $unit) {
            $unit->setStatus(PacaUnit::STATUS_RETURNED);
            $this->recordStatusTrace(
                unit: $unit,
                reason: $physicalReason,
                user: $currentUser,
                referenceType: 'sales_order',
                referenceId: $so->getId(),
                notes: sprintf($unitTraceTemplate, $unit->getSerial(), $so->getFolio()),
            );

            $key = $unit->getPaca()->getId() . '-' . $unit->getWarehouse()->getId();
            if (!isset($grouped[$key])) {
                $grouped[$key] = ['paca' => $unit->getPaca(), 'warehouse' => $unit->getWarehouse(), 'count' => 0];
            }
            $grouped[$key]['count']++;
        }

        foreach ($grouped as $group) {
            $this->inventoryManager->recordMovement(
                paca: $group['paca'],
                warehouse: $group['warehouse'],
                bin: null,
                reason: $returnReason,
                user: $currentUser,
                quantity: $group['count'],
                referenceType: 'sales_order',
                referenceId: $so->getId(),
                notes: sprintf($movementNotesTemplate, $so->getFolio(), $group['paca']->getCode()),
                forceAdjustment: true,
                affectsCachedStock: false,
            );
        }

        $this->em->flush();
        $this->em->commit();
    }

    private function reconcilePaymentStatusAfterReturn(SalesOrder $so, string $targetStatus): void
    {
        $currentPaymentStatus = $so->getPaymentStatus();

        if ($targetStatus === SalesOrder::STATUS_RETURNED) {
            if (in_array($currentPaymentStatus, [SalesOrder::PAYMENT_PAID, SalesOrder::PAYMENT_PARTIAL], true)) {
                $so->setPaymentStatus(SalesOrder::PAYMENT_REFUNDED);
            }

            return;
        }

        if ($targetStatus === SalesOrder::STATUS_PARTIALLY_RETURNED && $currentPaymentStatus === SalesOrder::PAYMENT_PAID) {
            $so->setPaymentStatus(SalesOrder::PAYMENT_PARTIAL);
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

    /**
     * @return array<int, array<array{id: int, serial: string, warehouseId: int, warehouseName: string, status: string}>>
     */
    private function getUnitsByOrderItems(SalesOrder $so): array
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
                'warehouseId' => $unit->getWarehouse()->getId(),
                'warehouseName' => $unit->getWarehouse()->getName(),
                'status' => $unit->getStatus(),
            ];
        }

        return $map;
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
