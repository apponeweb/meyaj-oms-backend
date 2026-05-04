<?php

declare(strict_types=1);

namespace App\Service;

use App\DTO\Request\CreatePurchaseOrderRequest;
use App\DTO\Request\ReceivePurchaseOrderRequest;
use App\DTO\Request\UpdatePurchaseOrderRequest;
use App\DTO\Response\PurchaseOrderDetailResponse;
use App\DTO\Response\PurchaseOrderResponse;
use App\Entity\Company;
use App\Entity\InventoryReason;
use App\Entity\LabelCatalog;
use App\Entity\Paca;
use App\Entity\PacaUnit;
use App\Entity\PurchaseOrder;
use App\Entity\PurchaseOrderItem;
use App\Entity\Supplier;
use App\Entity\User;
use App\Entity\Warehouse;
use App\Entity\WarehouseBin;
use App\Pagination\PaginatedResponse;
use App\Pagination\PaginationRequest;
use App\Pagination\Paginator;
use App\Repository\PurchaseOrderRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

final readonly class PurchaseOrderService
{
    public function __construct(
        private EntityManagerInterface $em,
        private PurchaseOrderRepository $purchaseOrderRepository,
        private Paginator $paginator,
        private FolioGenerator $folioGenerator,
        private InventoryManager $inventoryManager,
    ) {}

    public function list(PaginationRequest $pagination): PaginatedResponse
    {
        $qb = $this->purchaseOrderRepository->createPaginatedQueryBuilder(
            search: $pagination->search,
            supplierId: $pagination->supplierId,
            companyId: $pagination->companyId,
            status: $pagination->status,
        );

        $result = $this->paginator->paginate($qb, $pagination);

        return new PaginatedResponse(
            data: array_map(
                static fn (PurchaseOrder $po) => new PurchaseOrderResponse($po),
                $result->data,
            ),
            meta: $result->meta,
        );
    }

    public function show(int $id): PurchaseOrderDetailResponse
    {
        $po = $this->purchaseOrderRepository->createQueryBuilder('po')
            ->leftJoin('po.supplier', 's')
            ->leftJoin('po.company', 'c')
            ->leftJoin('po.user', 'u')
            ->leftJoin('po.items', 'i')
            ->leftJoin('i.label', 'l')
            ->leftJoin('i.paca', 'p')
            ->addSelect('s', 'c', 'u', 'i', 'l', 'p')
            ->where('po.id = :id')
            ->setParameter('id', $id)
            ->getQuery()
            ->getOneOrNullResult();

        if ($po === null) {
            throw new NotFoundHttpException(sprintf('Orden de compra con ID %d no encontrada.', $id));
        }

        return new PurchaseOrderDetailResponse($po);
    }

    public function create(CreatePurchaseOrderRequest $request, User $user): PurchaseOrderResponse
    {
        $company = $this->em->find(Company::class, $request->companyId);
        if ($company === null) {
            throw new NotFoundHttpException(sprintf('Empresa con ID %d no encontrada.', $request->companyId));
        }

        $supplier = $this->em->find(Supplier::class, $request->supplierId);
        if ($supplier === null) {
            throw new NotFoundHttpException(sprintf('Proveedor con ID %d no encontrado.', $request->supplierId));
        }

        $po = new PurchaseOrder();
        $po->setCompany($company);
        $po->setSupplier($supplier);
        $po->setUser($user);
        $po->setFolio($this->folioGenerator->generateWithDate('OC', PurchaseOrder::class));
        $po->setOrderDate(new \DateTime($request->orderDate));

        if ($request->expectedDate !== null) {
            $po->setExpectedDate(new \DateTime($request->expectedDate));
        }

        $po->setNotes($request->notes);

        $subtotal = '0.00';

        foreach ($request->items as $itemData) {
            $item = new PurchaseOrderItem();
            $item->setDescription($itemData['description']);
            $item->setExpectedQty((int) $itemData['expectedQty']);
            $item->setUnitPrice($itemData['unitPrice']);

            $itemTotal = bcmul($itemData['unitPrice'], (string) $itemData['expectedQty'], 2);
            $item->setTotalPrice($itemTotal);

            if (isset($itemData['labelId'])) {
                $label = $this->em->find(LabelCatalog::class, $itemData['labelId']);
                if ($label !== null) {
                    $item->setLabel($label);
                }
            }

            if (isset($itemData['pacaId'])) {
                $paca = $this->em->find(Paca::class, $itemData['pacaId']);
                if ($paca !== null) {
                    $item->setPaca($paca);
                    if ($item->getDescription() === '' || $item->getDescription() === $itemData['description']) {
                        $item->setDescription($paca->getName());
                    }
                }
            }

            $po->addItem($item);
            $subtotal = bcadd($subtotal, $itemTotal, 2);
        }

        $po->setSubtotal($subtotal);
        $po->setTotal($subtotal);

        $this->em->persist($po);
        $this->em->flush();

        return new PurchaseOrderResponse($po);
    }

    public function update(int $id, UpdatePurchaseOrderRequest $request): PurchaseOrderResponse
    {
        $po = $this->purchaseOrderRepository->find($id);
        if ($po === null) {
            throw new NotFoundHttpException(sprintf('Orden de compra con ID %d no encontrada.', $id));
        }

        if ($request->status !== null) {
            $po->setStatus($request->status);
        }

        if ($request->orderDate !== null) {
            $po->setOrderDate(new \DateTime($request->orderDate));
        }

        if ($request->expectedDate !== null) {
            $po->setExpectedDate(new \DateTime($request->expectedDate));
        }

        if ($request->notes !== null) {
            $po->setNotes($request->notes);
        }

        $this->em->flush();

        return new PurchaseOrderResponse($po);
    }

    public function delete(int $id): void
    {
        $po = $this->purchaseOrderRepository->find($id);
        if ($po === null) {
            throw new NotFoundHttpException(sprintf('Orden de compra con ID %d no encontrada.', $id));
        }

        if ($po->getStatus() !== PurchaseOrder::STATUS_DRAFT) {
            throw new BadRequestHttpException('Solo se pueden eliminar órdenes en estado BORRADOR.');
        }

        $this->em->remove($po);
        $this->em->flush();
    }

    public function receiveItems(int $id, ReceivePurchaseOrderRequest $request, User $user): PurchaseOrderDetailResponse
    {
        $po = $this->purchaseOrderRepository->createQueryBuilder('po')
            ->leftJoin('po.supplier', 's')
            ->leftJoin('po.company', 'c')
            ->leftJoin('po.user', 'u')
            ->leftJoin('po.items', 'i')
            ->leftJoin('i.label', 'l')
            ->leftJoin('i.paca', 'p')
            ->addSelect('s', 'c', 'u', 'i', 'l', 'p')
            ->where('po.id = :id')
            ->setParameter('id', $id)
            ->getQuery()
            ->getOneOrNullResult();

        if ($po === null) {
            throw new NotFoundHttpException(sprintf('Orden de compra con ID %d no encontrada.', $id));
        }

        if ($po->getStatus() === PurchaseOrder::STATUS_CANCELLED) {
            throw new BadRequestHttpException('No se puede recibir mercancía de una orden cancelada.');
        }

        $warehouse = $this->em->find(Warehouse::class, $request->warehouseId);
        if ($warehouse === null) {
            throw new NotFoundHttpException(sprintf('Bodega con ID %d no encontrada.', $request->warehouseId));
        }

        $warehouseBin = null;
        if ($request->warehouseBinId !== null) {
            $warehouseBin = $this->em->find(WarehouseBin::class, $request->warehouseBinId);
        }

        $purchaseReason = $this->em->getRepository(InventoryReason::class)->findOneBy(['code' => InventoryReason::CODE_PURCHASE]);
        if ($purchaseReason === null) {
            throw new BadRequestHttpException('No se encontró el motivo de inventario PURCHASE. Ejecute el seed de datos.');
        }

        $this->em->beginTransaction();

        try {
            $affectedPacas = [];
            $nextSerialNumbers = [];
            $itemsById = [];
            foreach ($po->getItems() as $item) {
                $itemsById[$item->getId()] = $item;
            }

            foreach ($request->items as $receiveData) {
                $itemId = (int) $receiveData['itemId'];
                $receivedQty = (int) $receiveData['receivedQty'];

                if (!isset($itemsById[$itemId])) {
                    throw new NotFoundHttpException(sprintf('Artículo con ID %d no encontrado en esta orden.', $itemId));
                }

                if ($receivedQty <= 0) {
                    continue;
                }

                $item = $itemsById[$itemId];
                $previousReceived = $item->getReceivedQty();
                $newQtyToReceive = $receivedQty - $previousReceived;

                $item->setReceivedQty($receivedQty);

                if (isset($receiveData['notes'])) {
                    $item->setNotes($receiveData['notes']);
                }

                if ($receivedQty >= $item->getExpectedQty()) {
                    $item->setStatus(PurchaseOrderItem::STATUS_RECEIVED);
                } elseif ($receivedQty > 0) {
                    $item->setStatus(PurchaseOrderItem::STATUS_PARTIAL);
                }

                // Solo crear paca si hay nuevas unidades recibidas
                if ($newQtyToReceive > 0) {
                    $linkedPaca = $item->getPaca();

                    if ($linkedPaca !== null) {
                        // Usar la paca vinculada al item
                        $this->inventoryManager->recordMovement(
                            paca: $linkedPaca,
                            warehouse: $warehouse,
                            bin: $warehouseBin,
                            reason: $purchaseReason,
                            user: $user,
                            quantity: $newQtyToReceive,
                            referenceType: 'purchase_order',
                            referenceId: $po->getId(),
                            unitCost: $item->getUnitPrice(),
                            notes: sprintf('Recepción de OC %s - %s', $po->getFolio(), $item->getDescription()),
                        );

                        $affectedPacas[$linkedPaca->getId()] = $linkedPaca;

                        // Create individual PacaUnit records
                        for ($i = 0; $i < $newQtyToReceive; $i++) {
                            $unit = new PacaUnit();
                            $unit->setSerial($this->generateNextUnitSerial($linkedPaca, $nextSerialNumbers));
                            $unit->setPaca($linkedPaca);
                            $unit->setWarehouse($warehouse);
                            if ($warehouseBin !== null) {
                                $unit->setWarehouseBin($warehouseBin);
                            }
                            $this->em->persist($unit);
                        }
                    } else {
                        $pacaCode = sprintf('OC-%s-%04d', $po->getFolio(), $item->getId());

                        // Buscar si ya existe una paca para este item (recepción parcial previa)
                        $existingPaca = $this->em->getRepository(Paca::class)->findOneBy(['code' => $pacaCode]);

                        if ($existingPaca !== null) {
                            // Actualizar paca existente via InventoryManager
                            $this->inventoryManager->recordMovement(
                                paca: $existingPaca,
                                warehouse: $warehouse,
                                bin: $warehouseBin,
                                reason: $purchaseReason,
                                user: $user,
                                quantity: $newQtyToReceive,
                                referenceType: 'purchase_order',
                                referenceId: $po->getId(),
                                unitCost: $item->getUnitPrice(),
                                notes: sprintf('Recepción de OC %s - %s', $po->getFolio(), $item->getDescription()),
                            );

                            $affectedPacas[$existingPaca->getId()] = $existingPaca;

                            // Create individual PacaUnit records
                            for ($i = 0; $i < $newQtyToReceive; $i++) {
                                $unit = new PacaUnit();
                                $unit->setSerial($this->generateNextUnitSerial($existingPaca, $nextSerialNumbers));
                                $unit->setPaca($existingPaca);
                                $unit->setWarehouse($warehouse);
                                if ($warehouseBin !== null) {
                                    $unit->setWarehouseBin($warehouseBin);
                                }
                                $this->em->persist($unit);
                            }
                        } else {
                            // Crear nueva paca
                            $paca = new Paca();
                            $paca->setCode($pacaCode);
                            $paca->setName($item->getDescription());
                            $paca->setPurchasePrice($item->getUnitPrice());
                            $paca->setSellingPrice($item->getUnitPrice());
                            $paca->setCachedStock(0); // Se actualiza via InventoryManager
                            $paca->setSupplier($po->getSupplier());

                            if ($item->getLabel() !== null) {
                                $paca->setLabel($item->getLabel());
                            }

                            $this->em->persist($paca);
                            $this->em->flush(); // Flush para obtener ID antes del movimiento

                            // Registrar movimiento de entrada
                            $this->inventoryManager->recordMovement(
                                paca: $paca,
                                warehouse: $warehouse,
                                bin: $warehouseBin,
                                reason: $purchaseReason,
                                user: $user,
                                quantity: $newQtyToReceive,
                                referenceType: 'purchase_order',
                                referenceId: $po->getId(),
                                unitCost: $item->getUnitPrice(),
                                notes: sprintf('Recepción de OC %s - %s', $po->getFolio(), $item->getDescription()),
                            );

                            $affectedPacas[$paca->getId()] = $paca;

                            // Create individual PacaUnit records
                            for ($i = 0; $i < $newQtyToReceive; $i++) {
                                $unit = new PacaUnit();
                                $unit->setSerial($this->generateNextUnitSerial($paca, $nextSerialNumbers));
                                $unit->setPaca($paca);
                                $unit->setWarehouse($warehouse);
                                $unit->setPurchaseOrder($po);
                                if ($warehouseBin !== null) {
                                    $unit->setWarehouseBin($warehouseBin);
                                }
                                $this->em->persist($unit);
                            }
                        }
                    }
                }
            }

            $this->em->flush();

            foreach ($affectedPacas as $paca) {
                $this->inventoryManager->updateCachedStock($paca);
            }

            // Actualizar estado de la PO
            $allReceived = true;
            $someReceived = false;
            foreach ($po->getItems() as $item) {
                if ($item->getStatus() === PurchaseOrderItem::STATUS_RECEIVED) {
                    $someReceived = true;
                } else {
                    $allReceived = false;
                }
                if ($item->getStatus() === PurchaseOrderItem::STATUS_PARTIAL) {
                    $someReceived = true;
                }
            }

            if ($allReceived) {
                $po->setStatus(PurchaseOrder::STATUS_RECEIVED);
                $po->setReceivedDate(new \DateTime());
            } elseif ($someReceived) {
                $po->setStatus(PurchaseOrder::STATUS_PARTIAL);
            }

            $this->em->flush();
            $this->em->commit();

            return new PurchaseOrderDetailResponse($po);

        } catch (\Exception $e) {
            $this->em->rollback();
            throw $e;
        }
    }

    /**
     * @param array<int, int> $nextSerialNumbers
     */
    private function generateNextUnitSerial(Paca $paca, array &$nextSerialNumbers): string
    {
        $pacaId = $paca->getId();

        if (!isset($nextSerialNumbers[$pacaId])) {
            $serials = $this->em->createQuery(
                'SELECT u.serial FROM App\Entity\PacaUnit u WHERE u.paca = :paca'
            )
                ->setParameter('paca', $paca)
                ->getSingleColumnResult();

            $max = 0;
            foreach ($serials as $serial) {
                if (!is_string($serial)) {
                    continue;
                }

                $suffix = strrchr($serial, '-');
                if ($suffix === false) {
                    continue;
                }

                $number = (int) ltrim($suffix, '-');
                if ($number > $max) {
                    $max = $number;
                }
            }

            $nextSerialNumbers[$pacaId] = $max + 1;
        }

        $nextNumber = $nextSerialNumbers[$pacaId]++;

        return sprintf('%s-%04d', $paca->getCode(), $nextNumber);
    }
}
