<?php

declare(strict_types=1);

namespace App\Controller;

use App\DTO\Request\CreateWarehouseTypeRequest;
use App\DTO\Request\UpdateWarehouseTypeRequest;
use App\Pagination\PaginationRequest;
use App\Service\WarehouseTypeService;
use Nelmio\ApiDocBundle\Attribute\Model;
use OpenApi\Attributes as OA;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\MapQueryString;
use Symfony\Component\HttpKernel\Attribute\MapRequestPayload;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/api/almacenes/warehouse-types')]
#[OA\Tag(name: 'Almacenes - Tipos de Bodega')]
final class WarehouseTypeController extends AbstractController
{
    public function __construct(
        private readonly WarehouseTypeService $warehouseTypeService,
    ) {
    }

    #[Route('', methods: ['GET'])]
    #[OA\Get(summary: 'Listar tipos de bodega con paginación')]
    #[OA\Response(response: 200, description: 'Lista paginada de tipos de bodega')]
    public function index(
        #[MapQueryString] ?PaginationRequest $pagination,
    ): JsonResponse {
        $pagination ??= new PaginationRequest();
        return $this->json($this->warehouseTypeService->list($pagination));
    }

    #[Route('/{id}', methods: ['GET'], requirements: ['id' => '\d+'])]
    #[OA\Get(summary: 'Obtener un tipo de bodega por ID')]
    #[OA\Response(response: 200, description: 'Tipo de bodega encontrado')]
    #[OA\Response(response: 404, description: 'Tipo de bodega no encontrado')]
    public function show(int $id): JsonResponse
    {
        return $this->json($this->warehouseTypeService->show($id));
    }

    #[Route('', methods: ['POST'])]
    #[OA\Post(
        summary: 'Crear un nuevo tipo de bodega',
        requestBody: new OA\RequestBody(
            content: new OA\JsonContent(ref: new Model(type: CreateWarehouseTypeRequest::class)),
        ),
    )]
    #[OA\Response(response: 201, description: 'Tipo de bodega creado exitosamente')]
    public function create(
        #[MapRequestPayload] CreateWarehouseTypeRequest $request,
    ): JsonResponse {
        return $this->json($this->warehouseTypeService->create($request), Response::HTTP_CREATED);
    }

    #[Route('/{id}', methods: ['PUT'], requirements: ['id' => '\d+'])]
    #[OA\Put(
        summary: 'Actualizar un tipo de bodega',
        requestBody: new OA\RequestBody(
            content: new OA\JsonContent(ref: new Model(type: UpdateWarehouseTypeRequest::class)),
        ),
    )]
    #[OA\Response(response: 200, description: 'Tipo de bodega actualizado')]
    #[OA\Response(response: 404, description: 'Tipo de bodega no encontrado')]
    public function update(
        int $id,
        #[MapRequestPayload] UpdateWarehouseTypeRequest $request,
    ): JsonResponse {
        return $this->json($this->warehouseTypeService->update($id, $request));
    }

    #[Route('/{id}', methods: ['DELETE'], requirements: ['id' => '\d+'])]
    #[OA\Delete(summary: 'Eliminar un tipo de bodega')]
    #[OA\Response(response: 204, description: 'Tipo de bodega eliminado')]
    #[OA\Response(response: 404, description: 'Tipo de bodega no encontrado')]
    public function delete(int $id): JsonResponse
    {
        $this->warehouseTypeService->delete($id);
        return $this->json(null, Response::HTTP_NO_CONTENT);
    }
}
