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
use App\Entity\InventoryReservation;
use App\Entity\Paca;
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

        return new SalesOrderDetailResponse($so);
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

            $folio = $this->folioGenerator->generate('SO', SalesOrder::class);
            $so->setFolio($folio);

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

                $subtotal = bcadd($subtotal, bcmul($unitPrice, (string) $quantity, 2), 2);
                $totalDiscount = bcadd($totalDiscount, $itemDiscount, 2);

                // Reserve stock
                $this->reserveStock($paca, $currentUser, $quantity);
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

            $this->em->persist($so);
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

        $this->em->beginTransaction();

        try {
            $so->setStatus($newStatus);

            // Status history
            $history = new SalesOrderStatusHistory();
            $history->setUser($currentUser);
            $history->setFromStatus($currentStatus);
            $history->setToStatus($newStatus);
            $history->setNotes($request->notes);
            $so->addStatusHistory($history);

            // Inventory side-effects
            if ($newStatus === SalesOrder::STATUS_SHIPPED) {
                $saleReason = $this->em->getRepository(InventoryReason::class)
                    ->findOneBy(['code' => 'SALE']);

                foreach ($so->getItems() as $item) {
                    // 1. Cumplir reservas
                    $this->fulfillReservation($item->getPaca(), $so->getId(), $item->getQuantity());

                    // 2. Registrar movimiento de SALIDA en el Kardex
                    $warehouse = $item->getPaca()->getWarehouse();
                    if ($warehouse !== null && $saleReason !== null) {
                        $this->inventoryManager->recordMovement(
                            paca: $item->getPaca(),
                            warehouse: $warehouse,
                            bin: $item->getPaca()->getWarehouseBin(),
                            reason: $saleReason,
                            user: $currentUser,
                            quantity: $item->getQuantity(),
                            referenceType: 'sales_order',
                            referenceId: $so->getId(),
                            unitCost: $item->getUnitPrice(),
                            notes: sprintf('Venta %s - %s', $so->getFolio(), $item->getPaca()->getCode()),
                        );
                    }
                }
                $so->setDeliveryStatus(SalesOrder::DELIVERY_SHIPPED);
            }

            if ($newStatus === SalesOrder::STATUS_DELIVERED) {
                $so->setDeliveredAt(new \DateTimeImmutable());
                $so->setDeliveryStatus(SalesOrder::DELIVERY_DELIVERED);

                // Si ya está pagado, marcar como venta completada
                if ($so->getPaymentStatus() === SalesOrder::PAYMENT_PAID) {
                    $so->setStatus(SalesOrder::STATUS_DELIVERED);
                }
            }

            if ($newStatus === SalesOrder::STATUS_CANCELLED) {
                $returnReason = $this->em->getRepository(InventoryReason::class)
                    ->findOneBy(['code' => 'RETURN']);

                foreach ($so->getItems() as $item) {
                    $this->releaseReservation($item->getPaca(), $so->getId(), $item->getQuantity());

                    // Si ya se había enviado (stock ya decrementado), revertir con movimiento IN
                    if ($currentStatus === SalesOrder::STATUS_SHIPPED || $currentStatus === SalesOrder::STATUS_DELIVERED) {
                        $warehouse = $item->getPaca()->getWarehouse();
                        if ($warehouse !== null && $returnReason !== null) {
                            $this->inventoryManager->recordMovement(
                                paca: $item->getPaca(),
                                warehouse: $warehouse,
                                bin: $item->getPaca()->getWarehouseBin(),
                                reason: $returnReason,
                                user: $currentUser,
                                quantity: $item->getQuantity(),
                                referenceType: 'sales_order',
                                referenceId: $so->getId(),
                                notes: sprintf('Cancelación pedido %s - %s', $so->getFolio(), $item->getPaca()->getCode()),
                            );
                        }
                    }
                }
            }

            if ($newStatus === SalesOrder::STATUS_RETURNED) {
                $returnReason = $this->em->getRepository(InventoryReason::class)
                    ->findOneBy(['code' => 'RETURN']);

                foreach ($so->getItems() as $item) {
                    // Devolver stock al inventario
                    $warehouse = $item->getPaca()->getWarehouse();
                    if ($warehouse !== null && $returnReason !== null) {
                        $this->inventoryManager->recordMovement(
                            paca: $item->getPaca(),
                            warehouse: $warehouse,
                            bin: $item->getPaca()->getWarehouseBin(),
                            reason: $returnReason,
                            user: $currentUser,
                            quantity: $item->getQuantity(),
                            referenceType: 'sales_order',
                            referenceId: $so->getId(),
                            notes: sprintf('Devolución pedido %s - %s', $so->getFolio(), $item->getPaca()->getCode()),
                        );
                    }
                }
            }

            $this->em->flush();
            $this->em->commit();

            return new SalesOrderDetailResponse($so);
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

        // Registrar en historial
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

        // Release reservations before deleting
        foreach ($so->getItems() as $item) {
            $this->releaseReservation($item->getPaca(), $so->getId(), $item->getQuantity());
        }

        $this->em->remove($so);
        $this->em->flush();
    }

    private function reserveStock(Paca $paca, User $user, int $quantity): void
    {
        $reservation = new InventoryReservation();
        $reservation->setPaca($paca);
        $reservation->setUser($user);
        $reservation->setQuantity($quantity);
        $reservation->setStatus('ACTIVE');
        $this->em->persist($reservation);
    }

    private function fulfillReservation(Paca $paca, int $salesOrderId, int $quantity): void
    {
        $reservations = $this->em->getRepository(InventoryReservation::class)
            ->findBy(['paca' => $paca, 'status' => 'ACTIVE']);

        $remaining = $quantity;
        foreach ($reservations as $reservation) {
            if ($remaining <= 0) break;
            $reservation->setStatus('FULFILLED');
            $reservation->setSalesOrderId($salesOrderId);
            $remaining -= $reservation->getQuantity();
        }

        // Stock se decrementa via InventoryManager.recordMovement() en changeStatus(SHIPPED)
    }

    private function releaseReservation(Paca $paca, int $salesOrderId, int $quantity): void
    {
        $reservations = $this->em->getRepository(InventoryReservation::class)
            ->findBy(['paca' => $paca, 'status' => 'ACTIVE']);

        $remaining = $quantity;
        foreach ($reservations as $reservation) {
            if ($remaining <= 0) break;
            $reservation->setStatus('RELEASED');
            $remaining -= $reservation->getQuantity();
        }
    }
}
