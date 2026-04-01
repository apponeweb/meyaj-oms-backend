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
use App\Entity\PurchaseOrder;
use App\Entity\PurchaseOrderItem;
use App\Entity\Supplier;
use App\Entity\User;
use App\Entity\Warehouse;
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
            ->addSelect('s', 'c', 'u', 'i', 'l')
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
        $po->setFolio($this->folioGenerator->generate('PO', PurchaseOrder::class));
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
            ->addSelect('s', 'c', 'u', 'i', 'l')
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

        $purchaseReason = $this->em->getRepository(InventoryReason::class)->findOneBy(['code' => 'PURCHASE']);
        if ($purchaseReason === null) {
            throw new BadRequestHttpException('No se encontró el motivo de inventario PURCHASE. Ejecute el seed de datos.');
        }

        $this->em->beginTransaction();

        try {
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
                    $pacaCode = sprintf('PO-%s-%04d', $po->getFolio(), $item->getId());

                    // Buscar si ya existe una paca para este item (recepción parcial previa)
                    $existingPaca = $this->em->getRepository(Paca::class)->findOneBy(['code' => $pacaCode]);

                    if ($existingPaca !== null) {
                        // Actualizar paca existente via InventoryManager
                        $this->inventoryManager->recordMovement(
                            paca: $existingPaca,
                            warehouse: $warehouse,
                            bin: null,
                            reason: $purchaseReason,
                            user: $user,
                            quantity: $newQtyToReceive,
                            referenceType: 'purchase_order',
                            referenceId: $po->getId(),
                            unitCost: $item->getUnitPrice(),
                            notes: sprintf('Recepción de OC %s - %s', $po->getFolio(), $item->getDescription()),
                        );
                    } else {
                        // Crear nueva paca
                        $paca = new Paca();
                        $paca->setCode($pacaCode);
                        $paca->setName($item->getDescription());
                        $paca->setPurchasePrice($item->getUnitPrice());
                        $paca->setSellingPrice($item->getUnitPrice());
                        $paca->setStock(0); // Se actualiza via InventoryManager
                        $paca->setWarehouse($warehouse);
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
                            bin: null,
                            reason: $purchaseReason,
                            user: $user,
                            quantity: $newQtyToReceive,
                            referenceType: 'purchase_order',
                            referenceId: $po->getId(),
                            unitCost: $item->getUnitPrice(),
                            notes: sprintf('Recepción de OC %s - %s', $po->getFolio(), $item->getDescription()),
                        );
                    }
                }
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
}
