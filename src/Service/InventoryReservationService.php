<?php

declare(strict_types=1);

namespace App\Service;

use App\DTO\Response\InventoryReservationResponse;
use App\Entity\InventoryReservation;
use App\Pagination\PaginatedResponse;
use App\Pagination\PaginationRequest;
use App\Pagination\Paginator;
use App\Repository\InventoryReservationRepository;

final readonly class InventoryReservationService
{
    public function __construct(
        private InventoryReservationRepository $reservationRepository,
        private InventoryManager $inventoryManager,
        private Paginator $paginator,
    ) {
    }

    public function list(PaginationRequest $pagination, ?int $pacaId = null, ?string $status = null): PaginatedResponse
    {
        $qb = $this->reservationRepository->createPaginatedQueryBuilder(
            pacaId: $pacaId,
            status: $status,
        );

        $result = $this->paginator->paginate($qb, $pagination, fetchJoinCollection: false);

        return new PaginatedResponse(
            data: array_map(
                static fn (InventoryReservation $r) => new InventoryReservationResponse($r),
                $result->data,
            ),
            meta: $result->meta,
        );
    }

    /**
     * @return array{stock: int, reserved: int, available: int}
     */
    public function getSummaryByPaca(int $pacaId): array
    {
        $paca = $this->reservationRepository->getEntityManager()
            ->getRepository(\App\Entity\Paca::class)
            ->find($pacaId);

        if ($paca === null) {
            throw new \Symfony\Component\HttpKernel\Exception\NotFoundHttpException(
                \sprintf('Paca con ID %d no encontrada.', $pacaId)
            );
        }

        $reserved = $this->reservationRepository->getActiveReservedQuantity($pacaId);

        return [
            'stock' => $paca->getStock(),
            'reserved' => $reserved,
            'available' => $paca->getStock() - $reserved,
        ];
    }
}
