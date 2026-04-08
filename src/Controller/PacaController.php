<?php

declare(strict_types=1);

namespace App\Controller;

use App\DTO\Request\CreatePacaRequest;
use App\DTO\Request\UpdatePacaRequest;
use App\Entity\User;
use App\Pagination\PaginationRequest;
use App\Service\PacaExcelService;
use App\Service\PacaService;
use Nelmio\ApiDocBundle\Attribute\Model;
use OpenApi\Attributes as OA;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Symfony\Component\HttpKernel\Attribute\MapQueryString;
use Symfony\Component\HttpKernel\Attribute\MapRequestPayload;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/api/productos/pacas')]
#[OA\Tag(name: 'Productos - Pacas')]
final class PacaController extends AbstractController
{
    public function __construct(
        private readonly PacaService $service,
        private readonly PacaExcelService $excelService,
    ) {}

    #[Route('', methods: ['GET'])]
    #[OA\Get(summary: 'Listar pacas con paginación y filtros')]
    public function index(Request $httpRequest, #[MapQueryString] ?PaginationRequest $p): JsonResponse
    {
        $brandId = $httpRequest->query->getInt('brandId') ?: null;
        $supplierId = $httpRequest->query->getInt('supplierId') ?: null;
        $active = $httpRequest->query->has('active') ? (bool)$httpRequest->query->get('active') : null;
        $companyId = $httpRequest->query->getInt('companyId') ?: null;
        $warehouseId = $httpRequest->query->getInt('warehouseId') ?: null;
        $warehouseBinId = $httpRequest->query->getInt('warehouseBinId') ?: null;

        return $this->json($this->service->list(
            $p ?? new PaginationRequest(),
            $brandId,
            $supplierId,
            $active,
            $companyId,
            $warehouseId,
            $warehouseBinId
        ));
    }

    #[Route('/next-code', methods: ['GET'])]
    #[OA\Get(summary: 'Obtener el siguiente codigo de paca disponible')]
    public function nextCode(): JsonResponse
    {
        return $this->json($this->service->getNextCode());
    }

    #[Route('/{id}', methods: ['GET'], requirements: ['id' => '\d+'])]
    #[OA\Get(summary: 'Obtener paca')]
    public function show(int $id): JsonResponse { return $this->json($this->service->show($id)); }

    #[Route('', methods: ['POST'])]
    #[OA\Post(summary: 'Crear paca', requestBody: new OA\RequestBody(content: new OA\JsonContent(ref: new Model(type: CreatePacaRequest::class))))]
    #[OA\Response(response: 201, description: 'Creada')]
    public function create(#[MapRequestPayload] CreatePacaRequest $r): JsonResponse
    { return $this->json($this->service->create($r), Response::HTTP_CREATED); }

    #[Route('/{id}', methods: ['PUT'], requirements: ['id' => '\d+'])]
    #[OA\Put(summary: 'Actualizar paca', requestBody: new OA\RequestBody(content: new OA\JsonContent(ref: new Model(type: UpdatePacaRequest::class))))]
    public function update(int $id, #[MapRequestPayload] UpdatePacaRequest $r): JsonResponse
    { return $this->json($this->service->update($id, $r)); }

    #[Route('/{id}', methods: ['DELETE'], requirements: ['id' => '\d+'])]
    #[OA\Delete(summary: 'Eliminar paca')]
    public function delete(int $id): JsonResponse
    { $this->service->delete($id); return $this->json(null, Response::HTTP_NO_CONTENT); }

    #[Route('/{id}/add-stock', methods: ['POST'], requirements: ['id' => '\d+'])]
    #[OA\Post(summary: 'Agregar stock directo a una paca')]
    #[OA\Response(response: 200, description: 'Stock agregado')]
    public function addStock(int $id, Request $request): JsonResponse
    {
        $payload = json_decode($request->getContent(), true, 512, \JSON_THROW_ON_ERROR);
        $warehouseId = (int) ($payload['warehouseId'] ?? 0);
        $warehouseBinId = isset($payload['warehouseBinId']) ? (int) $payload['warehouseBinId'] : null;
        $quantity = (int) ($payload['quantity'] ?? 0);

        if ($warehouseId <= 0 || $quantity <= 0) {
            return $this->json(['error' => ['message' => 'warehouseId y quantity son requeridos']], Response::HTTP_BAD_REQUEST);
        }

        $result = $this->service->addStock($id, $warehouseId, $warehouseBinId, $quantity, $this->getUser());
        return $this->json($result);
    }

    // ── Excel Export ─────────────────────────────────────────────────

    #[Route('/export', methods: ['GET'])]
    #[OA\Get(summary: 'Exportar pacas a Excel')]
    public function export(): StreamedResponse
    {
        return $this->excelService->export();
    }

    // ── Excel Import — Phase 1: upload file ──────────────────────────

    #[Route('/import/upload', methods: ['POST'])]
    #[OA\Post(summary: 'Subir archivo Excel para importación')]
    public function importUpload(Request $request): JsonResponse
    {
        $file = $request->files->get('file');
        if (!$file) {
            return $this->json(['error' => 'No se proporcionó archivo'], Response::HTTP_BAD_REQUEST);
        }

        $allowed = [
            'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            'application/vnd.ms-excel',
        ];
        if (!\in_array($file->getMimeType(), $allowed, true)) {
            return $this->json(['error' => 'El archivo debe ser un Excel (.xlsx)'], Response::HTTP_BAD_REQUEST);
        }

        /** @var User $user */
        $user = $this->getUser();

        $log = $this->excelService->uploadImportFile($file, $file->getClientOriginalName(), $user);

        return $this->json([
            'importId' => $log->getId(),
            'totalRows' => $log->getTotalRows(),
            'filename' => $log->getOriginalFilename(),
        ], Response::HTTP_CREATED);
    }

    // ── Excel Import — Phase 2: process ──────────────────────────────

    #[Route('/import/{id}/process', methods: ['POST'], requirements: ['id' => '\d+'])]
    #[OA\Post(summary: 'Ejecutar procesamiento de importación')]
    public function importProcess(int $id): JsonResponse
    {
        $result = $this->excelService->processImport($id);
        return $this->json($result);
    }

    // ── Import status (for polling) ──────────────────────────────────

    #[Route('/import/{id}/status', methods: ['GET'], requirements: ['id' => '\d+'])]
    #[OA\Get(summary: 'Consultar estado de una importación')]
    public function importStatus(int $id): JsonResponse
    {
        return $this->json($this->excelService->getImportStatus($id));
    }

    // ── Import History ───────────────────────────────────────────────

    #[Route('/import/history', methods: ['GET'])]
    #[OA\Get(summary: 'Historial de importaciones')]
    public function importHistory(): JsonResponse
    {
        return $this->json($this->excelService->getHistory());
    }
}
