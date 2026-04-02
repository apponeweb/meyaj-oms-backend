<?php

declare(strict_types=1);

namespace App\Controller;

use App\Pagination\PaginationRequest;
use App\Service\InventoryReservationService;
use OpenApi\Attributes as OA;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Attribute\MapQueryString;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/api/inventario/reservations')]
#[OA\Tag(name: 'Inventario - Reservas')]
final class InventoryReservationController extends AbstractController
{
    public function __construct(
        private readonly InventoryReservationService $reservationService,
    ) {
    }

    #[Route('', methods: ['GET'])]
    #[OA\Get(summary: 'Listar reservas de inventario con paginación')]
    #[OA\Response(response: 200, description: 'Lista paginada de reservas')]
    public function index(
        #[MapQueryString] ?PaginationRequest $pagination,
        Request $request,
    ): JsonResponse {
        $pagination ??= new PaginationRequest();

        return $this->json($this->reservationService->list(
            $pagination,
            pacaId: $request->query->has('pacaId') ? (int) $request->query->get('pacaId') : null,
            status: $request->query->get('status'),
        ));
    }

    #[Route('/summary/{pacaId}', methods: ['GET'], requirements: ['pacaId' => '\d+'])]
    #[OA\Get(summary: 'Resumen de stock y reservas para una paca')]
    #[OA\Response(response: 200, description: 'Resumen de stock')]
    public function summary(int $pacaId): JsonResponse
    {
        return $this->json($this->reservationService->getSummaryByPaca($pacaId));
    }
}
