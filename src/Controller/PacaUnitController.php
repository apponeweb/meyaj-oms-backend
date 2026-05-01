<?php

declare(strict_types=1);

namespace App\Controller;

use App\Entity\InventoryReason;
use App\Entity\User;
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

        $pacaId          = $httpRequest->query->getInt('pacaId') ?: null;
        $pacaIdsRaw      = $httpRequest->query->all('pacaIds');
        $pacaIds         = !empty($pacaIdsRaw)
            ? array_values(array_filter(array_map('intval', $pacaIdsRaw), fn(int $v) => $v > 0))
            : null;
        if ($pacaIds !== null && empty($pacaIds)) {
            $pacaIds = null;
        }
        $warehouseId     = $httpRequest->query->getInt('warehouseId') ?: null;
        $warehouseBinId  = $httpRequest->query->getInt('warehouseBinId') ?: null;
        $status          = $httpRequest->query->get('status') ?: null;
        $salesOrderId    = $httpRequest->query->getInt('salesOrderId') ?: null;
        $purchaseOrderId = $httpRequest->query->getInt('purchaseOrderId') ?: null;
        $labeledRaw      = $httpRequest->query->get('labeled');
        $labeled         = $labeledRaw !== null
            ? filter_var($labeledRaw, \FILTER_VALIDATE_BOOLEAN, \FILTER_NULL_ON_FAILURE)
            : null;

        return $this->json($this->pacaUnitService->list(
            $p, $pacaId, $warehouseId, $warehouseBinId,
            $status, $salesOrderId, $purchaseOrderId, $labeled, $pacaIds,
        ));
    }

    #[Route('/mark-labeled', methods: ['POST'])]
    #[OA\Post(summary: 'Marcar unidades como etiquetadas (lote)')]
    public function markLabeled(Request $request): JsonResponse
    {
        $data = json_decode($request->getContent(), true, 512, \JSON_THROW_ON_ERROR);
        $ids  = $data['ids'] ?? [];

        if (!is_array($ids) || empty($ids)) {
            return $this->json(['error' => 'Se requiere array de IDs en "ids".'], Response::HTTP_BAD_REQUEST);
        }

        $count = $this->pacaUnitService->markLabeled(array_map('intval', $ids));

        return $this->json([
            'updated' => $count,
            'message' => \sprintf('%d unidad(es) marcada(s) como etiquetada(s).', $count),
        ]);
    }

    #[Route('/reserve', methods: ['POST'])]
    #[OA\Post(summary: 'Reservar unidades en lote (solo AVAILABLE)')]
    public function reserve(Request $request): JsonResponse
    {
        $data = json_decode($request->getContent(), true, 512, \JSON_THROW_ON_ERROR);
        $ids  = $data['ids'] ?? [];

        if (!is_array($ids) || empty($ids)) {
            return $this->json(['error' => 'Se requiere array de IDs en "ids".'], Response::HTTP_BAD_REQUEST);
        }

        $result = $this->pacaUnitService->reserveBulk(array_map('intval', $ids));

        return $this->json([
            'reserved' => $result['reserved'],
            'skipped'  => $result['skipped'],
            'message'  => \sprintf(
                '%d unidad(es) reservada(s).%s',
                $result['reserved'],
                $result['skipped'] > 0 ? \sprintf(' %d omitida(s) por no estar disponibles.', $result['skipped']) : '',
            ),
        ]);
    }

    #[Route('/delete', methods: ['POST'])]
    #[OA\Post(summary: 'Eliminar unidades en lote (solo AVAILABLE)')]
    public function deleteBulk(Request $request): JsonResponse
    {
        $data = json_decode($request->getContent(), true, 512, \JSON_THROW_ON_ERROR);
        $ids  = $data['ids'] ?? [];

        if (!is_array($ids) || empty($ids)) {
            return $this->json(['error' => 'Se requiere array de IDs en "ids".'], Response::HTTP_BAD_REQUEST);
        }

        $result = $this->pacaUnitService->deleteBulk($ids);

        return $this->json([
            'deleted' => $result['deleted'],
            'skipped' => $result['skipped'],
            'message' => \sprintf(
                '%d unidad(es) eliminada(s).%s',
                $result['deleted'],
                $result['skipped'] > 0 ? \sprintf(' %d omitida(s) por no estar disponibles o no existir.', $result['skipped']) : '',
            ),
        ]);
    }

    #[Route('/transfer', methods: ['POST'])]
    #[OA\Post(summary: 'Traspasar unidades seleccionadas a otra bodega')]
    public function transferBulk(Request $request): JsonResponse
    {
        $data = json_decode($request->getContent(), true, 512, \JSON_THROW_ON_ERROR);
        $ids = $data['ids'] ?? [];
        $warehouseId = isset($data['warehouseId']) ? (int) $data['warehouseId'] : null;
        $reasonId = isset($data['reasonId']) ? (int) $data['reasonId'] : null;

        if (!is_array($ids) || empty($ids)) {
            return $this->json(['error' => 'Se requiere array de IDs en "ids".'], Response::HTTP_BAD_REQUEST);
        }

        if ($warehouseId === null || $warehouseId <= 0) {
            return $this->json(['error' => 'warehouseId es requerido.'], Response::HTTP_BAD_REQUEST);
        }

        if ($reasonId === null || $reasonId <= 0) {
            return $this->json(['error' => 'reasonId es requerido.'], Response::HTTP_BAD_REQUEST);
        }

        $warehouse = $this->em->getRepository(Warehouse::class)->find($warehouseId);
        if ($warehouse === null) {
            return $this->json(['error' => sprintf('Bodega con ID %d no encontrada.', $warehouseId)], Response::HTTP_NOT_FOUND);
        }

        $reason = $this->em->getRepository(InventoryReason::class)->find($reasonId);
        if ($reason === null) {
            return $this->json(['error' => sprintf('Motivo de movimiento con ID %d no encontrado.', $reasonId)], Response::HTTP_NOT_FOUND);
        }

        $bin = null;
        $warehouseBinId = isset($data['warehouseBinId']) ? (int) $data['warehouseBinId'] : null;
        if ($warehouseBinId !== null && $warehouseBinId > 0) {
            $bin = $this->em->getRepository(WarehouseBin::class)->find($warehouseBinId);
            if ($bin === null) {
                return $this->json(['error' => sprintf('Ubicación con ID %d no encontrada.', $warehouseBinId)], Response::HTTP_NOT_FOUND);
            }
        }

        /** @var User $user */
        $user = $this->getUser();

        $result = $this->pacaUnitService->transferBulk(
            array_values(array_filter(array_map('intval', $ids), static fn (int $id) => $id > 0)),
            $warehouse,
            $bin,
            $reason,
            $user,
        );

        return $this->json($result);
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
