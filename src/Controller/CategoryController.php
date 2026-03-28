<?php

declare(strict_types=1);

namespace App\Controller;

use App\DTO\Request\CreateCategoryRequest;
use App\DTO\Request\UpdateCategoryRequest;
use App\Pagination\PaginationRequest;
use App\Service\CategoryService;
use Nelmio\ApiDocBundle\Attribute\Model;
use OpenApi\Attributes as OA;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\MapQueryString;
use Symfony\Component\HttpKernel\Attribute\MapRequestPayload;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/api/categories')]
#[OA\Tag(name: 'Categories')]
final class CategoryController extends AbstractController
{
    public function __construct(
        private readonly CategoryService $categoryService,
    ) {
    }

    #[Route('', methods: ['GET'])]
    #[OA\Get(
        summary: 'Listar categorías con paginación',
        parameters: [
            new OA\Parameter(name: 'page', in: 'query', schema: new OA\Schema(type: 'integer', default: 1)),
            new OA\Parameter(name: 'limit', in: 'query', schema: new OA\Schema(type: 'integer', default: 10)),
            new OA\Parameter(name: 'sort', in: 'query', schema: new OA\Schema(type: 'string', enum: ['name', 'createdAt'])),
            new OA\Parameter(name: 'order', in: 'query', schema: new OA\Schema(type: 'string', enum: ['asc', 'desc'], default: 'asc')),
            new OA\Parameter(name: 'search', in: 'query', schema: new OA\Schema(type: 'string')),
        ],
    )]
    #[OA\Response(response: 200, description: 'Lista paginada de categorías')]
    public function index(
        #[MapQueryString] ?PaginationRequest $pagination,
    ): JsonResponse {
        $pagination ??= new PaginationRequest();

        return $this->json($this->categoryService->list($pagination));
    }

    #[Route('/{id}', methods: ['GET'], requirements: ['id' => '\d+'])]
    #[OA\Get(summary: 'Obtener una categoría por ID')]
    #[OA\Response(response: 200, description: 'Categoría encontrada')]
    #[OA\Response(response: 404, description: 'Categoría no encontrada')]
    public function show(int $id): JsonResponse
    {
        return $this->json($this->categoryService->show($id));
    }

    #[Route('', methods: ['POST'])]
    #[OA\Post(
        summary: 'Crear una nueva categoría',
        requestBody: new OA\RequestBody(
            content: new OA\JsonContent(ref: new Model(type: CreateCategoryRequest::class)),
        ),
    )]
    #[OA\Response(response: 201, description: 'Categoría creada exitosamente')]
    #[OA\Response(response: 422, description: 'Datos de validación inválidos')]
    public function create(
        #[MapRequestPayload] CreateCategoryRequest $request,
    ): JsonResponse {
        return $this->json(
            $this->categoryService->create($request),
            Response::HTTP_CREATED,
        );
    }

    #[Route('/{id}', methods: ['PUT'], requirements: ['id' => '\d+'])]
    #[OA\Put(
        summary: 'Actualizar una categoría',
        requestBody: new OA\RequestBody(
            content: new OA\JsonContent(ref: new Model(type: UpdateCategoryRequest::class)),
        ),
    )]
    #[OA\Response(response: 200, description: 'Categoría actualizada')]
    #[OA\Response(response: 404, description: 'Categoría no encontrada')]
    public function update(
        int $id,
        #[MapRequestPayload] UpdateCategoryRequest $request,
    ): JsonResponse {
        return $this->json($this->categoryService->update($id, $request));
    }

    #[Route('/{id}', methods: ['DELETE'], requirements: ['id' => '\d+'])]
    #[OA\Delete(summary: 'Eliminar una categoría')]
    #[OA\Response(response: 204, description: 'Categoría eliminada')]
    #[OA\Response(response: 404, description: 'Categoría no encontrada')]
    public function delete(int $id): JsonResponse
    {
        $this->categoryService->delete($id);

        return $this->json(null, Response::HTTP_NO_CONTENT);
    }
}
