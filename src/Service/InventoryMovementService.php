<?php

declare(strict_types=1);

namespace App\Service;

use App\DTO\Request\CreateInventoryMovementRequest;
use App\DTO\Response\InventoryMovementResponse;
use App\Entity\InventoryReason;
use App\Entity\Paca;
use App\Entity\User;
use App\Entity\Warehouse;
use App\Entity\WarehouseBin;
use App\Pagination\PaginatedResponse;
use App\Pagination\PaginationRequest;
use App\Pagination\Paginator;
use App\Repository\InventoryMovementRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

final readonly class InventoryMovementService
{
    public function __construct(
        private EntityManagerInterface $em,
        private InventoryMovementRepository $movementRepository,
        private InventoryManager $inventoryManager,
        private Paginator $paginator,
    ) {
    }

    public function list(PaginationRequest $pagination): PaginatedResponse
    {
        $qb = $this->movementRepository->createPaginatedQueryBuilder(
            search: $pagination->search,
            pacaId: $pagination->pacaId ?? null,
            warehouseId: $pagination->warehouseId ?? null,
            movementType: $pagination->movementType ?? null,
            dateFrom: $pagination->dateFrom ?? null,
            dateTo: $pagination->dateTo ?? null,
        );

        $result = $this->paginator->paginate($qb, $pagination, fetchJoinCollection: false);

        return new PaginatedResponse(
            data: array_map(
                static fn (\App\Entity\InventoryMovement $m) => new InventoryMovementResponse($m),
                $result->data,
            ),
            meta: $result->meta,
        );
    }

    public function show(int $id): InventoryMovementResponse
    {
        $movement = $this->movementRepository->find($id);
        if ($movement === null) {
            throw new NotFoundHttpException(sprintf('Movimiento de inventario con ID %d no encontrado.', $id));
        }
        return new InventoryMovementResponse($movement);
    }

    public function createManualMovement(CreateInventoryMovementRequest $request, User $user): InventoryMovementResponse
    {
        $paca = $this->em->getRepository(Paca::class)->find($request->pacaId);
        if ($paca === null) {
            throw new NotFoundHttpException(sprintf('Paca con ID %d no encontrada.', $request->pacaId));
        }

        $warehouse = $this->em->getRepository(Warehouse::class)->find($request->warehouseId);
        if ($warehouse === null) {
            throw new NotFoundHttpException(sprintf('Bodega con ID %d no encontrada.', $request->warehouseId));
        }

        $bin = null;
        if ($request->warehouseBinId !== null) {
            $bin = $this->em->getRepository(WarehouseBin::class)->find($request->warehouseBinId);
            if ($bin === null) {
                throw new NotFoundHttpException(sprintf('Ubicación de bodega con ID %d no encontrada.', $request->warehouseBinId));
            }
        }

        $reason = $this->em->getRepository(InventoryReason::class)->find($request->reasonId);
        if ($reason === null) {
            throw new NotFoundHttpException(sprintf('Motivo de inventario con ID %d no encontrado.', $request->reasonId));
        }

        $movement = $this->inventoryManager->recordMovement(
            paca: $paca,
            warehouse: $warehouse,
            bin: $bin,
            reason: $reason,
            user: $user,
            quantity: $request->quantity,
            unitCost: $request->unitCost,
            notes: $request->notes,
        );

        return new InventoryMovementResponse($movement);
    }
}
