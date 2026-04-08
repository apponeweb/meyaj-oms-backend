<?php

declare(strict_types=1);

namespace App\Controller;

use App\Entity\Warehouse;
use App\Entity\WarehouseBin;
use App\Pagination\PaginationRequest;
use App\Service\PacaUnitService;
use Doctrine\ORM\EntityManagerInterface;
use OpenApi\Attributes as OA;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\MapQueryString;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/api/inventario/paca-units')]
#[OA\Tag(name: 'Inventario - Unidades de Paca')]
final class PacaUnitController extends AbstractController
{
    public function __construct(
        private readonly PacaUnitService $pacaUnitService,
        private readonly EntityManagerInterface $em,
    ) {}

    #[Route('', methods: ['GET'])]
    #[OA\Get(summary: 'Listar unidades de paca con paginación y filtros')]
    public function index(Request $httpRequest, #[MapQueryString] ?PaginationRequest $p): JsonResponse
    {
        $p ??= new PaginationRequest();

        $pacaId = $httpRequest->query->getInt('pacaId') ?: null;
        $warehouseId = $httpRequest->query->getInt('warehouseId') ?: null;
        $warehouseBinId = $httpRequest->query->getInt('warehouseBinId') ?: null;
        $status = $httpRequest->query->get('status') ?: null;
        $salesOrderId = $httpRequest->query->getInt('salesOrderId') ?: null;
        $purchaseOrderId = $httpRequest->query->getInt('purchaseOrderId') ?: null;

        return $this->json($this->pacaUnitService->list(
            $p,
            $pacaId,
            $warehouseId,
            $warehouseBinId,
            $status,
            $salesOrderId,
            $purchaseOrderId,
        ));
    }

    #[Route('/{id}', methods: ['GET'], requirements: ['id' => '\d+'])]
    #[OA\Get(summary: 'Obtener unidad de paca por ID')]
    public function show(int $id): JsonResponse
    {
        return $this->json($this->pacaUnitService->show($id));
    }

    #[Route('/serial/{serial}', methods: ['GET'])]
    #[OA\Get(summary: 'Buscar unidad de paca por serial')]
    public function findBySerial(string $serial): JsonResponse
    {
        return $this->json($this->pacaUnitService->findBySerial($serial));
    }

    #[Route('/{id}/move', methods: ['POST'], requirements: ['id' => '\d+'])]
    #[OA\Post(summary: 'Mover unidad de paca a otra bodega')]
    public function move(int $id, Request $request): JsonResponse
    {
        $data = json_decode($request->getContent(), true, 512, \JSON_THROW_ON_ERROR);

        $warehouseId = $data['warehouseId'] ?? null;
        if ($warehouseId === null) {
            return $this->json(['error' => 'warehouseId es requerido'], Response::HTTP_BAD_REQUEST);
        }

        $warehouse = $this->em->getRepository(Warehouse::class)->find($warehouseId);
        if ($warehouse === null) {
            return $this->json(['error' => \sprintf('Bodega con ID %d no encontrada', $warehouseId)], Response::HTTP_NOT_FOUND);
        }

        $bin = null;
        $warehouseBinId = $data['warehouseBinId'] ?? null;
        if ($warehouseBinId !== null) {
            $bin = $this->em->getRepository(WarehouseBin::class)->find($warehouseBinId);
            if ($bin === null) {
                return $this->json(['error' => \sprintf('Ubicación con ID %d no encontrada', $warehouseBinId)], Response::HTTP_NOT_FOUND);
            }
        }

        return $this->json($this->pacaUnitService->move($id, $warehouse, $bin));
    }
}
