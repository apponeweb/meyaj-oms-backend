<?php

declare(strict_types=1);

namespace App\Controller;

use App\Pagination\PaginationRequest;
use App\Service\InventorySnapshotService;
use OpenApi\Attributes as OA;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpKernel\Attribute\MapQueryString;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/api/inventario/snapshots')]
#[OA\Tag(name: 'Inventario - Snapshots')]
final class InventorySnapshotController extends AbstractController
{
    public function __construct(
        private readonly InventorySnapshotService $snapshotService,
    ) {
    }

    #[Route('', methods: ['GET'])]
    #[OA\Get(summary: 'Listar snapshots de inventario')]
    #[OA\Response(response: 200, description: 'Lista paginada de snapshots')]
    public function index(
        #[MapQueryString] ?PaginationRequest $pagination,
    ): JsonResponse {
        $pagination ??= new PaginationRequest();
        return $this->json($this->snapshotService->list($pagination));
    }

    #[Route('/generate', methods: ['POST'])]
    #[OA\Post(summary: 'Generar snapshots de inventario para hoy')]
    #[OA\Response(response: 200, description: 'Snapshots generados')]
    public function generate(): JsonResponse
    {
        $count = $this->snapshotService->generateSnapshot();
        return $this->json(['message' => sprintf('%d snapshots generados.', $count), 'count' => $count]);
    }
}
