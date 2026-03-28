<?php

declare(strict_types=1);

namespace App\Controller;

use App\DTO\Request\CreateProductRequest;
use App\DTO\Request\UpdateProductRequest;
use App\Pagination\PaginationRequest;
use App\Service\ProductService;
use Nelmio\ApiDocBundle\Attribute\Model;
use OpenApi\Attributes as OA;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\MapQueryString;
use Symfony\Component\HttpKernel\Attribute\MapRequestPayload;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/api/products')]
#[OA\Tag(name: 'Products')]
final class ProductController extends AbstractController
{
    public function __construct(
        private readonly ProductService $productService,
    ) {
    }

    #[Route('', methods: ['GET'])]
    #[OA\Get(
        summary: 'Listar productos con paginación y filtros',
        parameters: [
            new OA\Parameter(name: 'page', in: 'query', schema: new OA\Schema(type: 'integer', default: 1)),
            new OA\Parameter(name: 'limit', in: 'query', schema: new OA\Schema(type: 'integer', default: 10)),
            new OA\Parameter(name: 'sort', in: 'query', schema: new OA\Schema(type: 'string', enum: ['name', 'price', 'createdAt'])),
            new OA\Parameter(name: 'order', in: 'query', schema: new OA\Schema(type: 'string', enum: ['asc', 'desc'], default: 'asc')),
            new OA\Parameter(name: 'search', in: 'query', schema: new OA\Schema(type: 'string')),
            new OA\Parameter(name: 'categoryId', in: 'query', schema: new OA\Schema(type: 'integer')),
            new OA\Parameter(name: 'active', in: 'query', schema: new OA\Schema(type: 'boolean')),
            new OA\Parameter(name: 'minPrice', in: 'query', schema: new OA\Schema(type: 'string')),
            new OA\Parameter(name: 'maxPrice', in: 'query', schema: new OA\Schema(type: 'string')),
        ],
    )]
    #[OA\Response(response: 200, description: 'Lista paginada de productos')]
    public function index(
        #[MapQueryString] ?PaginationRequest $pagination,
        Request $request,
    ): JsonResponse {
        $pagination ??= new PaginationRequest();

        return $this->json(
            $this->productService->list(
                pagination: $pagination,
                categoryId: $request->query->has('categoryId')
                    ? $request->query->getInt('categoryId')
                    : null,
                active: $request->query->has('active')
                    ? $request->query->getBoolean('active')
                    : null,
                minPrice: $request->query->get('minPrice'),
                maxPrice: $request->query->get('maxPrice'),
            ),
        );
    }

    #[Route('/{id}', methods: ['GET'], requirements: ['id' => '\d+'])]
    #[OA\Get(summary: 'Obtener un producto por ID')]
    #[OA\Response(response: 200, description: 'Producto encontrado')]
    #[OA\Response(response: 404, description: 'Producto no encontrado')]
    public function show(int $id): JsonResponse
    {
        return $this->json($this->productService->show($id));
    }

    #[Route('', methods: ['POST'])]
    #[OA\Post(
        summary: 'Crear un nuevo producto',
        requestBody: new OA\RequestBody(
            content: new OA\JsonContent(ref: new Model(type: CreateProductRequest::class)),
        ),
    )]
    #[OA\Response(response: 201, description: 'Producto creado exitosamente')]
    #[OA\Response(response: 422, description: 'Datos de validación inválidos')]
    public function create(
        #[MapRequestPayload] CreateProductRequest $request,
    ): JsonResponse {
        return $this->json(
            $this->productService->create($request),
            Response::HTTP_CREATED,
        );
    }

    #[Route('/{id}', methods: ['PUT'], requirements: ['id' => '\d+'])]
    #[OA\Put(
        summary: 'Actualizar un producto',
        requestBody: new OA\RequestBody(
            content: new OA\JsonContent(ref: new Model(type: UpdateProductRequest::class)),
        ),
    )]
    #[OA\Response(response: 200, description: 'Producto actualizado')]
    #[OA\Response(response: 404, description: 'Producto no encontrado')]
    public function update(
        int $id,
        #[MapRequestPayload] UpdateProductRequest $request,
    ): JsonResponse {
        return $this->json($this->productService->update($id, $request));
    }

    #[Route('/{id}', methods: ['DELETE'], requirements: ['id' => '\d+'])]
    #[OA\Delete(summary: 'Eliminar un producto')]
    #[OA\Response(response: 204, description: 'Producto eliminado')]
    #[OA\Response(response: 404, description: 'Producto no encontrado')]
    public function delete(int $id): JsonResponse
    {
        $this->productService->delete($id);

        return $this->json(null, Response::HTTP_NO_CONTENT);
    }
}
