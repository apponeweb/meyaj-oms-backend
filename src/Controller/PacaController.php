<?php

declare(strict_types=1);

namespace App\Controller;

use App\DTO\Request\CreatePacaRequest;
use App\DTO\Request\UpdatePacaRequest;
use App\Pagination\PaginationRequest;
use App\Service\PacaService;
use Nelmio\ApiDocBundle\Attribute\Model;
use OpenApi\Attributes as OA;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\MapQueryString;
use Symfony\Component\HttpKernel\Attribute\MapRequestPayload;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/api/productos/pacas')]
#[OA\Tag(name: 'Productos - Pacas')]
final class PacaController extends AbstractController
{
    public function __construct(private readonly PacaService $service) {}

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
}
