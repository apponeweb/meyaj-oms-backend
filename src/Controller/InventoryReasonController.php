<?php

declare(strict_types=1);

namespace App\Controller;

use App\DTO\Request\CreateInventoryReasonRequest;
use App\DTO\Request\UpdateInventoryReasonRequest;
use App\Pagination\PaginationRequest;
use App\Service\InventoryReasonService;
use Nelmio\ApiDocBundle\Attribute\Model;
use OpenApi\Attributes as OA;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\MapQueryString;
use Symfony\Component\HttpKernel\Attribute\MapRequestPayload;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/api/inventario/reasons')]
#[OA\Tag(name: 'Inventario - Motivos')]
final class InventoryReasonController extends AbstractController
{
    public function __construct(
        private readonly InventoryReasonService $reasonService,
    ) {
    }

    #[Route('', methods: ['GET'])]
    #[OA\Get(summary: 'Listar motivos de inventario con paginación')]
    #[OA\Response(response: 200, description: 'Lista paginada de motivos')]
    public function index(
        #[MapQueryString] ?PaginationRequest $pagination,
    ): JsonResponse {
        $pagination ??= new PaginationRequest();
        return $this->json($this->reasonService->list($pagination));
    }

    #[Route('/{id}', methods: ['GET'], requirements: ['id' => '\d+'])]
    #[OA\Get(summary: 'Obtener un motivo de inventario por ID')]
    #[OA\Response(response: 200, description: 'Motivo encontrado')]
    #[OA\Response(response: 404, description: 'Motivo no encontrado')]
    public function show(int $id): JsonResponse
    {
        return $this->json($this->reasonService->show($id));
    }

    #[Route('', methods: ['POST'])]
    #[OA\Post(
        summary: 'Crear un nuevo motivo de inventario',
        requestBody: new OA\RequestBody(
            content: new OA\JsonContent(ref: new Model(type: CreateInventoryReasonRequest::class)),
        ),
    )]
    #[OA\Response(response: 201, description: 'Motivo creado exitosamente')]
    public function create(
        #[MapRequestPayload] CreateInventoryReasonRequest $request,
    ): JsonResponse {
        return $this->json($this->reasonService->create($request), Response::HTTP_CREATED);
    }

    #[Route('/{id}', methods: ['PUT'], requirements: ['id' => '\d+'])]
    #[OA\Put(
        summary: 'Actualizar un motivo de inventario',
        requestBody: new OA\RequestBody(
            content: new OA\JsonContent(ref: new Model(type: UpdateInventoryReasonRequest::class)),
        ),
    )]
    #[OA\Response(response: 200, description: 'Motivo actualizado')]
    #[OA\Response(response: 404, description: 'Motivo no encontrado')]
    public function update(
        int $id,
        #[MapRequestPayload] UpdateInventoryReasonRequest $request,
    ): JsonResponse {
        return $this->json($this->reasonService->update($id, $request));
    }

    #[Route('/{id}', methods: ['DELETE'], requirements: ['id' => '\d+'])]
    #[OA\Delete(summary: 'Eliminar un motivo de inventario')]
    #[OA\Response(response: 204, description: 'Motivo eliminado')]
    #[OA\Response(response: 404, description: 'Motivo no encontrado')]
    public function delete(int $id): JsonResponse
    {
        $this->reasonService->delete($id);
        return $this->json(null, Response::HTTP_NO_CONTENT);
    }
}
