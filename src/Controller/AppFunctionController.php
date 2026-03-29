<?php

declare(strict_types=1);

namespace App\Controller;

use App\DTO\Request\CreateAppFunctionRequest;
use App\DTO\Request\UpdateAppFunctionRequest;
use App\Pagination\PaginationRequest;
use App\Service\AppFunctionService;
use Nelmio\ApiDocBundle\Attribute\Model;
use OpenApi\Attributes as OA;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\MapQueryString;
use Symfony\Component\HttpKernel\Attribute\MapRequestPayload;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/api/seguridad/functions')]
#[OA\Tag(name: 'Seguridad - Funcionalidades')]
final class AppFunctionController extends AbstractController
{
    public function __construct(
        private readonly AppFunctionService $appFunctionService,
    ) {
    }

    #[Route('', methods: ['GET'])]
    #[OA\Get(summary: 'Listar funcionalidades con paginación')]
    #[OA\Parameter(name: 'moduleId', in: 'query', schema: new OA\Schema(type: 'integer'))]
    #[OA\Response(response: 200, description: 'Lista paginada de funcionalidades')]
    public function index(
        Request $httpRequest,
        #[MapQueryString] ?PaginationRequest $pagination = null,
    ): JsonResponse {
        $pagination ??= new PaginationRequest();
        $moduleId = $httpRequest->query->getInt('moduleId') ?: null;
        return $this->json($this->appFunctionService->list($pagination, $moduleId));
    }

    #[Route('', methods: ['POST'])]
    #[OA\Post(
        summary: 'Crear una nueva funcionalidad',
        requestBody: new OA\RequestBody(
            content: new OA\JsonContent(ref: new Model(type: CreateAppFunctionRequest::class)),
        ),
    )]
    #[OA\Response(response: 201, description: 'Funcionalidad creada exitosamente')]
    public function create(
        #[MapRequestPayload] CreateAppFunctionRequest $request,
    ): JsonResponse {
        return $this->json($this->appFunctionService->create($request), Response::HTTP_CREATED);
    }

    #[Route('/{id}', methods: ['PUT'], requirements: ['id' => '\d+'])]
    #[OA\Put(
        summary: 'Actualizar una funcionalidad',
        requestBody: new OA\RequestBody(
            content: new OA\JsonContent(ref: new Model(type: UpdateAppFunctionRequest::class)),
        ),
    )]
    #[OA\Response(response: 200, description: 'Funcionalidad actualizada')]
    #[OA\Response(response: 404, description: 'Funcionalidad no encontrada')]
    public function update(
        int $id,
        #[MapRequestPayload] UpdateAppFunctionRequest $request,
    ): JsonResponse {
        return $this->json($this->appFunctionService->update($id, $request));
    }

    #[Route('/{id}', methods: ['DELETE'], requirements: ['id' => '\d+'])]
    #[OA\Delete(summary: 'Eliminar una funcionalidad')]
    #[OA\Response(response: 204, description: 'Funcionalidad eliminada')]
    #[OA\Response(response: 404, description: 'Funcionalidad no encontrada')]
    public function delete(int $id): JsonResponse
    {
        $this->appFunctionService->delete($id);
        return $this->json(null, Response::HTTP_NO_CONTENT);
    }
}
