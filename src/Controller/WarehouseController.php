<?php

declare(strict_types=1);

namespace App\Controller;

use App\DTO\Request\CreateWarehouseRequest;
use App\DTO\Request\UpdateWarehouseRequest;
use App\Pagination\PaginationRequest;
use App\Service\WarehouseService;
use Nelmio\ApiDocBundle\Attribute\Model;
use OpenApi\Attributes as OA;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\MapQueryString;
use Symfony\Component\HttpKernel\Attribute\MapRequestPayload;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/api/almacenes/warehouses')]
#[OA\Tag(name: 'Almacenes - Bodegas')]
final class WarehouseController extends AbstractController
{
    public function __construct(
        private readonly WarehouseService $warehouseService,
    ) {
    }

    #[Route('', methods: ['GET'])]
    #[OA\Get(summary: 'Listar bodegas con paginación')]
    #[OA\Response(response: 200, description: 'Lista paginada de bodegas')]
    public function index(
        #[MapQueryString] ?PaginationRequest $pagination,
    ): JsonResponse {
        $pagination ??= new PaginationRequest();
        return $this->json($this->warehouseService->list($pagination));
    }

    #[Route('/next-code', methods: ['GET'])]
    #[OA\Get(summary: 'Obtener el siguiente código sugerido para una bodega')]
    #[OA\Response(response: 200, description: 'Código sugerido')]
    public function nextCode(): JsonResponse
    {
        return $this->json(['code' => $this->warehouseService->nextCode()]);
    }

    #[Route('/{id}', methods: ['GET'], requirements: ['id' => '\d+'])]
    #[OA\Get(summary: 'Obtener una bodega por ID')]
    #[OA\Response(response: 200, description: 'Bodega encontrada')]
    #[OA\Response(response: 404, description: 'Bodega no encontrada')]
    public function show(int $id): JsonResponse
    {
        return $this->json($this->warehouseService->show($id));
    }

    #[Route('', methods: ['POST'])]
    #[OA\Post(
        summary: 'Crear una nueva bodega',
        requestBody: new OA\RequestBody(
            content: new OA\JsonContent(ref: new Model(type: CreateWarehouseRequest::class)),
        ),
    )]
    #[OA\Response(response: 201, description: 'Bodega creada exitosamente')]
    public function create(
        #[MapRequestPayload] CreateWarehouseRequest $request,
    ): JsonResponse {
        return $this->json($this->warehouseService->create($request), Response::HTTP_CREATED);
    }

    #[Route('/{id}', methods: ['PUT'], requirements: ['id' => '\d+'])]
    #[OA\Put(
        summary: 'Actualizar una bodega',
        requestBody: new OA\RequestBody(
            content: new OA\JsonContent(ref: new Model(type: UpdateWarehouseRequest::class)),
        ),
    )]
    #[OA\Response(response: 200, description: 'Bodega actualizada')]
    #[OA\Response(response: 404, description: 'Bodega no encontrada')]
    public function update(
        int $id,
        #[MapRequestPayload] UpdateWarehouseRequest $request,
    ): JsonResponse {
        return $this->json($this->warehouseService->update($id, $request));
    }

    #[Route('/{id}', methods: ['DELETE'], requirements: ['id' => '\d+'])]
    #[OA\Delete(summary: 'Eliminar una bodega')]
    #[OA\Response(response: 204, description: 'Bodega eliminada')]
    #[OA\Response(response: 404, description: 'Bodega no encontrada')]
    public function delete(int $id): JsonResponse
    {
        $this->warehouseService->delete($id);
        return $this->json(null, Response::HTTP_NO_CONTENT);
    }
}
