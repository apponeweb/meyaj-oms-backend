<?php

declare(strict_types=1);

namespace App\Controller;

use App\DTO\Request\CreateCompanyRequest;
use App\DTO\Request\UpdateCompanyRequest;
use App\Pagination\PaginationRequest;
use App\Service\CompanyService;
use Nelmio\ApiDocBundle\Attribute\Model;
use OpenApi\Attributes as OA;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\MapQueryString;
use Symfony\Component\HttpKernel\Attribute\MapRequestPayload;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/api/catalogos/companies')]
#[OA\Tag(name: 'Catálogos - Empresas')]
final class CompanyController extends AbstractController
{
    public function __construct(
        private readonly CompanyService $companyService,
    ) {
    }

    #[Route('', methods: ['GET'])]
    #[OA\Get(summary: 'Listar empresas con paginación')]
    #[OA\Response(response: 200, description: 'Lista paginada de empresas')]
    public function index(
        #[MapQueryString] ?PaginationRequest $pagination,
    ): JsonResponse {
        $pagination ??= new PaginationRequest();
        return $this->json($this->companyService->list($pagination));
    }

    #[Route('/{id}', methods: ['GET'], requirements: ['id' => '\d+'])]
    #[OA\Get(summary: 'Obtener una empresa por ID')]
    #[OA\Response(response: 200, description: 'Empresa encontrada')]
    #[OA\Response(response: 404, description: 'Empresa no encontrada')]
    public function show(int $id): JsonResponse
    {
        return $this->json($this->companyService->show($id));
    }

    #[Route('', methods: ['POST'])]
    #[OA\Post(
        summary: 'Crear una nueva empresa',
        requestBody: new OA\RequestBody(
            content: new OA\JsonContent(ref: new Model(type: CreateCompanyRequest::class)),
        ),
    )]
    #[OA\Response(response: 201, description: 'Empresa creada exitosamente')]
    public function create(
        #[MapRequestPayload] CreateCompanyRequest $request,
    ): JsonResponse {
        return $this->json($this->companyService->create($request), Response::HTTP_CREATED);
    }

    #[Route('/{id}', methods: ['PUT'], requirements: ['id' => '\d+'])]
    #[OA\Put(
        summary: 'Actualizar una empresa',
        requestBody: new OA\RequestBody(
            content: new OA\JsonContent(ref: new Model(type: UpdateCompanyRequest::class)),
        ),
    )]
    #[OA\Response(response: 200, description: 'Empresa actualizada')]
    #[OA\Response(response: 404, description: 'Empresa no encontrada')]
    public function update(
        int $id,
        #[MapRequestPayload] UpdateCompanyRequest $request,
    ): JsonResponse {
        return $this->json($this->companyService->update($id, $request));
    }

    #[Route('/{id}', methods: ['DELETE'], requirements: ['id' => '\d+'])]
    #[OA\Delete(summary: 'Eliminar una empresa')]
    #[OA\Response(response: 204, description: 'Empresa eliminada')]
    #[OA\Response(response: 404, description: 'Empresa no encontrada')]
    public function delete(int $id): JsonResponse
    {
        $this->companyService->delete($id);
        return $this->json(null, Response::HTTP_NO_CONTENT);
    }
}
