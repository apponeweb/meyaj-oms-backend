<?php

declare(strict_types=1);

namespace App\Controller;

use App\DTO\Request\CreateBranchRequest;
use App\DTO\Request\UpdateBranchRequest;
use App\Pagination\PaginationRequest;
use App\Service\BranchService;
use Nelmio\ApiDocBundle\Attribute\Model;
use OpenApi\Attributes as OA;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\MapQueryString;
use Symfony\Component\HttpKernel\Attribute\MapRequestPayload;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/api/catalogos/branches')]
#[OA\Tag(name: 'Catálogos - Sucursales')]
final class BranchController extends AbstractController
{
    public function __construct(
        private readonly BranchService $branchService,
    ) {
    }

    #[Route('', methods: ['GET'])]
    #[OA\Get(summary: 'Listar sucursales con paginación')]
    #[OA\Parameter(name: 'companyId', in: 'query', schema: new OA\Schema(type: 'integer'))]
    #[OA\Response(response: 200, description: 'Lista paginada de sucursales')]
    public function index(
        Request $httpRequest,
        #[MapQueryString] ?PaginationRequest $pagination,
    ): JsonResponse {
        $pagination ??= new PaginationRequest();
        $companyId = $httpRequest->query->getInt('companyId') ?: null;
        return $this->json($this->branchService->list($pagination, $companyId));
    }

    #[Route('/{id}', methods: ['GET'], requirements: ['id' => '\d+'])]
    #[OA\Get(summary: 'Obtener una sucursal por ID')]
    #[OA\Response(response: 200, description: 'Sucursal encontrada')]
    #[OA\Response(response: 404, description: 'Sucursal no encontrada')]
    public function show(int $id): JsonResponse
    {
        return $this->json($this->branchService->show($id));
    }

    #[Route('', methods: ['POST'])]
    #[OA\Post(
        summary: 'Crear una nueva sucursal',
        requestBody: new OA\RequestBody(
            content: new OA\JsonContent(ref: new Model(type: CreateBranchRequest::class)),
        ),
    )]
    #[OA\Response(response: 201, description: 'Sucursal creada exitosamente')]
    public function create(
        #[MapRequestPayload] CreateBranchRequest $request,
    ): JsonResponse {
        return $this->json($this->branchService->create($request), Response::HTTP_CREATED);
    }

    #[Route('/{id}', methods: ['PUT'], requirements: ['id' => '\d+'])]
    #[OA\Put(
        summary: 'Actualizar una sucursal',
        requestBody: new OA\RequestBody(
            content: new OA\JsonContent(ref: new Model(type: UpdateBranchRequest::class)),
        ),
    )]
    #[OA\Response(response: 200, description: 'Sucursal actualizada')]
    #[OA\Response(response: 404, description: 'Sucursal no encontrada')]
    public function update(
        int $id,
        #[MapRequestPayload] UpdateBranchRequest $request,
    ): JsonResponse {
        return $this->json($this->branchService->update($id, $request));
    }

    #[Route('/{id}', methods: ['DELETE'], requirements: ['id' => '\d+'])]
    #[OA\Delete(summary: 'Eliminar una sucursal')]
    #[OA\Response(response: 204, description: 'Sucursal eliminada')]
    #[OA\Response(response: 404, description: 'Sucursal no encontrada')]
    public function delete(int $id): JsonResponse
    {
        $this->branchService->delete($id);
        return $this->json(null, Response::HTTP_NO_CONTENT);
    }
}
