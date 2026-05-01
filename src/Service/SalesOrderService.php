<?php

declare(strict_types=1);

namespace App\Service;

use App\DTO\Request\ChangeStatusRequest;
use App\DTO\Request\CreateSalesOrderRequest;
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
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

final readonly class SalesOrderService
{
    private const ALLOWED_TRANSITIONS = [
        SalesOrder::STATUS_PENDING => [SalesOrder::STATUS_CONFIRMED, SalesOrder::STATUS_CANCELLED],
        SalesOrder::STATUS_CONFIRMED => [SalesOrder::STATUS_PREPARING, SalesOrder::STATUS_SHIPPED, SalesOrder::STATUS_CANCELLED],
        SalesOrder::STATUS_PREPARING => [SalesOrder::STATUS_SHIPPED, SalesOrder::STATUS_CANCELLED],
        SalesOrder::STATUS_SHIPPED => [SalesOrder::STATUS_DELIVERED, SalesOrder::STATUS_RETURNED],
        SalesOrder::STATUS_DELIVERED => [SalesOrder::STATUS_RETURNED],
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

                // Reserve PacaUnits atomically
                $this->inventoryManager->reserveStock($paca, $so, $item, $quantity);
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

            // Auto-confirm POS sales and immediately dispatch
            if ($request->channel === SalesOrder::CHANNEL_POS) {
                $saleReason = $this->requireInventoryReason(InventoryReason::CODE_SALE);
                $physicalReason = $this->requireInventoryReason(InventoryReason::CODE_PHYSICAL);

                // PENDING → CONFIRMED
                $so->setStatus(SalesOrder::STATUS_CONFIRMED);
                $confirmHistory = new SalesOrderStatusHistory();
                $confirmHistory->setUser($currentUser);
                $confirmHistory->setFromStatus(SalesOrder::STATUS_PENDING);
                $confirmHistory->setToStatus(SalesOrder::STATUS_CONFIRMED);
                $confirmHistory->setNotes('Auto-confirmado (venta POS)');
                $so->addStatusHistory($confirmHistory);

                // CONFIRMED → SHIPPED (immediate dispatch for POS)
                $so->setStatus(SalesOrder::STATUS_SHIPPED);
                $so->setDeliveryStatus(SalesOrder::DELIVERY_SHIPPED);
                $shippedHistory = new SalesOrderStatusHistory();
                $shippedHistory->setUser($currentUser);
                $shippedHistory->setFromStatus(SalesOrder::STATUS_CONFIRMED);
                $shippedHistory->setToStatus(SalesOrder::STATUS_SHIPPED);
                $shippedHistory->setNotes('Despacho inmediato (venta POS)');
                $so->addStatusHistory($shippedHistory);

                // SHIPPED → DELIVERED
                $so->setStatus(SalesOrder::STATUS_DELIVERED);
                $so->setDeliveryStatus(SalesOrder::DELIVERY_DELIVERED);
                $so->setDeliveredAt(new \DateTimeImmutable());
                $deliveredHistory = new SalesOrderStatusHistory();
                $deliveredHistory->setUser($currentUser);
                $deliveredHistory->setFromStatus(SalesOrder::STATUS_SHIPPED);
                $deliveredHistory->setToStatus(SalesOrder::STATUS_DELIVERED);
                $deliveredHistory->setNotes('Entrega inmediata (venta POS)');
                $so->addStatusHistory($deliveredHistory);

                $units = $this->em->getRepository(PacaUnit::class)->findBy([
                    'salesOrder' => $so,
                    'status' => PacaUnit::STATUS_RESERVED,
                ]);

                $grouped = [];
                foreach ($units as $unit) {
                    $unit->setStatus(PacaUnit::STATUS_SOLD);
                    $this->recordStatusTrace(
                        unit: $unit,
                        reason: $physicalReason,
                        user: $currentUser,
                        referenceType: 'sales_order',
                        referenceId: $so->getId(),
                        notes: sprintf('Unidad %s marcada como SOLD en venta POS %s', $unit->getSerial(), $so->getFolio()),
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
                        reason: $saleReason,
                        user: $currentUser,
                        quantity: $group['count'],
                        referenceType: 'sales_order',
                        referenceId: $so->getId(),
                        notes: sprintf('Venta POS %s - %s', $so->getFolio(), $group['paca']->getCode()),
                    );
                }

                // Mark payment as PAID for POS
                $so->setPaymentStatus(SalesOrder::PAYMENT_PAID);
            }

            $this->em->flush();
            $this->em->commit();

            return new SalesOrderResponse($so);
        } catch (\Exception $e) {
            $this->em->rollback();
            throw $e;
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

        // SHIPPED and DELIVERED must go through the Dispatch module (ShipmentService)
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

        $this->em->beginTransaction();

        try {
            $so->setStatus($newStatus);

            $physicalReason = $this->requireInventoryReason(InventoryReason::CODE_PHYSICAL);

            // Status history
            $history = new SalesOrderStatusHistory();
            $history->setUser($currentUser);
            $history->setFromStatus($currentStatus);
            $history->setToStatus($newStatus);
            $history->setNotes($request->notes);
            $so->addStatusHistory($history);

            // Inventory side-effects (SHIPPED/DELIVERED now handled by ShipmentService)
            if ($newStatus === SalesOrder::STATUS_SHIPPED) {
                $saleReason = $this->requireInventoryReason(InventoryReason::CODE_SALE);

                // Get all reserved/picked units for this order, grouped by paca+warehouse
                $units = $this->em->getRepository(PacaUnit::class)->findBy([
                    'salesOrder' => $so,
                ]);

                // Group units by paca and warehouse for batch movement recording
                $grouped = [];
                foreach ($units as $unit) {
                    if (in_array($unit->getStatus(), [PacaUnit::STATUS_RESERVED, PacaUnit::STATUS_PICKED], true)) {
                        $unit->setStatus(PacaUnit::STATUS_DISPATCHED);
                        $this->recordStatusTrace(
                            unit: $unit,
                            reason: $physicalReason,
                            user: $currentUser,
                            referenceType: 'sales_order',
                            referenceId: $so->getId(),
                            notes: sprintf('Unidad %s marcada como DISPATCHED en pedido %s', $unit->getSerial(), $so->getFolio()),
                        );
                        $key = $unit->getPaca()->getId() . '-' . $unit->getWarehouse()->getId();
                        if (!isset($grouped[$key])) {
                            $grouped[$key] = ['paca' => $unit->getPaca(), 'warehouse' => $unit->getWarehouse(), 'count' => 0];
                        }
                        $grouped[$key]['count']++;
                    }
                }

                // Record one movement per paca-warehouse combination
                foreach ($grouped as $group) {
                    $this->inventoryManager->recordMovement(
                        paca: $group['paca'],
                        warehouse: $group['warehouse'],
                        bin: null,
                        reason: $saleReason,
                        user: $currentUser,
                        quantity: $group['count'],
                        referenceType: 'sales_order',
                        referenceId: $so->getId(),
                        notes: sprintf('Venta %s - %s', $so->getFolio(), $group['paca']->getCode()),
                    );
                }

                $so->setDeliveryStatus(SalesOrder::DELIVERY_SHIPPED);
            }

            if ($newStatus === SalesOrder::STATUS_DELIVERED) {
                $so->setDeliveredAt(new \DateTimeImmutable());
                $so->setDeliveryStatus(SalesOrder::DELIVERY_DELIVERED);

                // Mark all dispatched units as SOLD
                $units = $this->em->getRepository(PacaUnit::class)->findBy([
                    'salesOrder' => $so,
                    'status' => PacaUnit::STATUS_DISPATCHED,
                ]);
                foreach ($units as $unit) {
                    $unit->setStatus(PacaUnit::STATUS_SOLD);
                    $this->recordStatusTrace(
                        unit: $unit,
                        reason: $physicalReason,
                        user: $currentUser,
                        referenceType: 'sales_order',
                        referenceId: $so->getId(),
                        notes: sprintf('Unidad %s marcada como SOLD en pedido %s', $unit->getSerial(), $so->getFolio()),
                    );
                }

                if ($so->getPaymentStatus() === SalesOrder::PAYMENT_PAID) {
                    $so->setStatus(SalesOrder::STATUS_DELIVERED);
                }
            }

            if ($newStatus === SalesOrder::STATUS_CANCELLED) {
                $returnReason = $this->requireInventoryReason(InventoryReason::CODE_RETURN);

                // Release all reserved/picked units
                $this->inventoryManager->releaseAllUnitsForOrder($so);

                // If already shipped/delivered, also return stock
                if ($currentStatus === SalesOrder::STATUS_SHIPPED || $currentStatus === SalesOrder::STATUS_DELIVERED) {
                    $units = $this->em->getRepository(PacaUnit::class)->findBy([
                        'salesOrder' => $so,
                        'status' => PacaUnit::STATUS_DISPATCHED,
                    ]);

                    $grouped = [];
                    foreach ($units as $unit) {
                        $unit->setStatus(PacaUnit::STATUS_RETURNED);
                        $unit->setSalesOrder(null);
                        $unit->setSalesOrderItem(null);
                        $this->recordStatusTrace(
                            unit: $unit,
                            reason: $physicalReason,
                            user: $currentUser,
                            referenceType: 'sales_order',
                            referenceId: $so->getId(),
                            notes: sprintf('Unidad %s marcada como RETURNED por cancelación del pedido %s', $unit->getSerial(), $so->getFolio()),
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
                            notes: sprintf('Cancelación pedido %s - %s', $so->getFolio(), $group['paca']->getCode()),
                            forceAdjustment: true,
                        );
                    }
                }
            }

            if ($newStatus === SalesOrder::STATUS_RETURNED) {
                $returnReason = $this->requireInventoryReason(InventoryReason::CODE_RETURN);

                $units = $this->em->getRepository(PacaUnit::class)->findBy([
                    'salesOrder' => $so,
                ]);

                $grouped = [];
                foreach ($units as $unit) {
                    if (in_array($unit->getStatus(), [PacaUnit::STATUS_DISPATCHED, PacaUnit::STATUS_SOLD], true)) {
                        $unit->setStatus(PacaUnit::STATUS_RETURNED);
                        $this->recordStatusTrace(
                            unit: $unit,
                            reason: $physicalReason,
                            user: $currentUser,
                            referenceType: 'sales_order',
                            referenceId: $so->getId(),
                            notes: sprintf('Unidad %s marcada como RETURNED en pedido %s', $unit->getSerial(), $so->getFolio()),
                        );
                        $key = $unit->getPaca()->getId() . '-' . $unit->getWarehouse()->getId();
                        if (!isset($grouped[$key])) {
                            $grouped[$key] = ['paca' => $unit->getPaca(), 'warehouse' => $unit->getWarehouse(), 'count' => 0];
                        }
                        $grouped[$key]['count']++;
                    }
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
                        notes: sprintf('Devolución pedido %s - %s', $so->getFolio(), $group['paca']->getCode()),
                        forceAdjustment: true,
                    );
                }
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

    private const ALLOWED_PAYMENT_TRANSITIONS = [
        SalesOrder::PAYMENT_PENDING => [SalesOrder::PAYMENT_PARTIAL, SalesOrder::PAYMENT_PAID],
        SalesOrder::PAYMENT_PARTIAL => [SalesOrder::PAYMENT_PAID, SalesOrder::PAYMENT_REFUNDED],
        SalesOrder::PAYMENT_PAID => [SalesOrder::PAYMENT_REFUNDED],
        SalesOrder::PAYMENT_REFUNDED => [],
    ];

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

        $currentPayment = $so->getPaymentStatus();
        $newPayment = $request->status;

        $allowed = self::ALLOWED_PAYMENT_TRANSITIONS[$currentPayment] ?? [];
        if (!in_array($newPayment, $allowed, true)) {
            throw new BadRequestHttpException(
                sprintf('No se puede cambiar el estado de pago de "%s" a "%s".', $currentPayment, $newPayment),
            );
        }

        $so->setPaymentStatus($newPayment);

        $history = new SalesOrderStatusHistory();
        $history->setUser($currentUser);
        $history->setFromStatus('PAGO:' . $currentPayment);
        $history->setToStatus('PAGO:' . $newPayment);
        $history->setNotes($request->notes);
        $so->addStatusHistory($history);

        $this->em->flush();

        return new SalesOrderDetailResponse($so);
    }

    public function delete(int $id): void
    {
        $so = $this->salesOrderRepository->find($id);
        if ($so === null) {
            throw new NotFoundHttpException(sprintf('Pedido de venta con ID %d no encontrado.', $id));
        }

        if ($so->getStatus() !== SalesOrder::STATUS_PENDING) {
            throw new BadRequestHttpException('Solo se pueden eliminar pedidos en estado PENDIENTE.');
        }

        // Release all reserved PacaUnits
        $this->inventoryManager->releaseAllUnitsForOrder($so);

        $this->em->remove($so);
        $this->em->flush();
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
