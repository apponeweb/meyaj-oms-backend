<?php

declare(strict_types=1);

namespace App\Controller;

use App\DTO\Request\CreateWarehouseBinRequest;
use App\DTO\Request\UpdateWarehouseBinRequest;
use App\Pagination\PaginationRequest;
use App\Service\WarehouseBinService;
use Nelmio\ApiDocBundle\Attribute\Model;
use OpenApi\Attributes as OA;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\MapQueryString;
use Symfony\Component\HttpKernel\Attribute\MapRequestPayload;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/api/almacenes/bins')]
#[OA\Tag(name: 'Almacenes - Ubicaciones')]
final class WarehouseBinController extends AbstractController
{
    public function __construct(
        private readonly WarehouseBinService $binService,
    ) {
    }

    #[Route('', methods: ['GET'])]
    #[OA\Get(summary: 'Listar ubicaciones con paginación')]
    #[OA\Response(response: 200, description: 'Lista paginada de ubicaciones')]
    public function index(
        #[MapQueryString] ?PaginationRequest $pagination,
    ): JsonResponse {
        $pagination ??= new PaginationRequest();
        return $this->json($this->binService->list($pagination));
    }

    #[Route('/{id}', methods: ['GET'], requirements: ['id' => '\d+'])]
    #[OA\Get(summary: 'Obtener una ubicación por ID')]
    #[OA\Response(response: 200, description: 'Ubicación encontrada')]
    #[OA\Response(response: 404, description: 'Ubicación no encontrada')]
    public function show(int $id): JsonResponse
    {
        return $this->json($this->binService->show($id));
    }

    #[Route('', methods: ['POST'])]
    #[OA\Post(
        summary: 'Crear una nueva ubicación',
        requestBody: new OA\RequestBody(
            content: new OA\JsonContent(ref: new Model(type: CreateWarehouseBinRequest::class)),
        ),
    )]
    #[OA\Response(response: 201, description: 'Ubicación creada exitosamente')]
    public function create(
        #[MapRequestPayload] CreateWarehouseBinRequest $request,
    ): JsonResponse {
        return $this->json($this->binService->create($request), Response::HTTP_CREATED);
    }

    #[Route('/{id}', methods: ['PUT'], requirements: ['id' => '\d+'])]
    #[OA\Put(
        summary: 'Actualizar una ubicación',
        requestBody: new OA\RequestBody(
            content: new OA\JsonContent(ref: new Model(type: UpdateWarehouseBinRequest::class)),
        ),
    )]
    #[OA\Response(response: 200, description: 'Ubicación actualizada')]
    #[OA\Response(response: 404, description: 'Ubicación no encontrada')]
    public function update(
        int $id,
        #[MapRequestPayload] UpdateWarehouseBinRequest $request,
    ): JsonResponse {
        return $this->json($this->binService->update($id, $request));
    }

    #[Route('/{id}', methods: ['DELETE'], requirements: ['id' => '\d+'])]
    #[OA\Delete(summary: 'Eliminar una ubicación')]
    #[OA\Response(response: 204, description: 'Ubicación eliminada')]
    #[OA\Response(response: 404, description: 'Ubicación no encontrada')]
    public function delete(int $id): JsonResponse
    {
        $this->binService->delete($id);
        return $this->json(null, Response::HTTP_NO_CONTENT);
    }
}
