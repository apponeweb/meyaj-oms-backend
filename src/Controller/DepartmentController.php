<?php

declare(strict_types=1);

namespace App\Controller;

use App\DTO\Request\CreateDepartmentRequest;
use App\DTO\Request\UpdateDepartmentRequest;
use App\Pagination\PaginationRequest;
use App\Service\DepartmentService;
use Nelmio\ApiDocBundle\Attribute\Model;
use OpenApi\Attributes as OA;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\MapQueryString;
use Symfony\Component\HttpKernel\Attribute\MapRequestPayload;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/api/catalogos/departments')]
#[OA\Tag(name: 'Catálogos - Departamentos')]
final class DepartmentController extends AbstractController
{
    public function __construct(
        private readonly DepartmentService $departmentService,
    ) {
    }

    #[Route('', methods: ['GET'])]
    #[OA\Get(summary: 'Listar departamentos con paginación')]
    #[OA\Parameter(name: 'companyId', in: 'query', schema: new OA\Schema(type: 'integer'))]
    #[OA\Response(response: 200, description: 'Lista paginada de departamentos')]
    public function index(
        Request $httpRequest,
        #[MapQueryString] ?PaginationRequest $pagination,
    ): JsonResponse {
        $pagination ??= new PaginationRequest();
        $companyId = $httpRequest->query->getInt('companyId') ?: null;
        return $this->json($this->departmentService->list($pagination, $companyId));
    }

    #[Route('/{id}', methods: ['GET'], requirements: ['id' => '\d+'])]
    #[OA\Get(summary: 'Obtener un departamento por ID')]
    #[OA\Response(response: 200, description: 'Departamento encontrado')]
    #[OA\Response(response: 404, description: 'Departamento no encontrado')]
    public function show(int $id): JsonResponse
    {
        return $this->json($this->departmentService->show($id));
    }

    #[Route('', methods: ['POST'])]
    #[OA\Post(
        summary: 'Crear un nuevo departamento',
        requestBody: new OA\RequestBody(
            content: new OA\JsonContent(ref: new Model(type: CreateDepartmentRequest::class)),
        ),
    )]
    #[OA\Response(response: 201, description: 'Departamento creado exitosamente')]
    public function create(
        #[MapRequestPayload] CreateDepartmentRequest $request,
    ): JsonResponse {
        return $this->json($this->departmentService->create($request), Response::HTTP_CREATED);
    }

    #[Route('/{id}', methods: ['PUT'], requirements: ['id' => '\d+'])]
    #[OA\Put(
        summary: 'Actualizar un departamento',
        requestBody: new OA\RequestBody(
            content: new OA\JsonContent(ref: new Model(type: UpdateDepartmentRequest::class)),
        ),
    )]
    #[OA\Response(response: 200, description: 'Departamento actualizado')]
    #[OA\Response(response: 404, description: 'Departamento no encontrado')]
    public function update(
        int $id,
        #[MapRequestPayload] UpdateDepartmentRequest $request,
    ): JsonResponse {
        return $this->json($this->departmentService->update($id, $request));
    }

    #[Route('/{id}', methods: ['DELETE'], requirements: ['id' => '\d+'])]
    #[OA\Delete(summary: 'Eliminar un departamento')]
    #[OA\Response(response: 204, description: 'Departamento eliminado')]
    #[OA\Response(response: 404, description: 'Departamento no encontrado')]
    public function delete(int $id): JsonResponse
    {
        $this->departmentService->delete($id);
        return $this->json(null, Response::HTTP_NO_CONTENT);
    }
}
