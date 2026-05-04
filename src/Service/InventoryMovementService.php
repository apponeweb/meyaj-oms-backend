<?php

declare(strict_types=1);

namespace App\Service;

use App\DTO\Request\CreateInventoryMovementRequest;
use App\DTO\Response\InventoryMovementResponse;
use App\Entity\User;
use App\Pagination\PaginatedResponse;
use App\Pagination\PaginationRequest;
use App\Pagination\Paginator;
use App\Repository\InventoryMovementRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
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
        throw new BadRequestHttpException(
            'Los movimientos manuales de inventario están deshabilitados en este flujo porque desincronizan el kardex del stock sustentado por unidades. Use recepción, despacho, devoluciones o ajustes por conteo físico.',
        );
    }
}
