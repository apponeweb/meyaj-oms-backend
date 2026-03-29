<?php

declare(strict_types=1);

namespace App\Controller;

use App\DTO\Request\CreateAppModuleRequest;
use App\DTO\Request\UpdateAppModuleRequest;
use App\Pagination\PaginationRequest;
use App\Service\AppModuleService;
use Nelmio\ApiDocBundle\Attribute\Model;
use OpenApi\Attributes as OA;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\MapQueryString;
use Symfony\Component\HttpKernel\Attribute\MapRequestPayload;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/api/seguridad/modules')]
#[OA\Tag(name: 'Seguridad - Módulos')]
final class AppModuleController extends AbstractController
{
    public function __construct(
        private readonly AppModuleService $appModuleService,
    ) {
    }

    #[Route('', methods: ['GET'])]
    #[OA\Get(summary: 'Listar módulos con paginación')]
    #[OA\Response(response: 200, description: 'Lista paginada de módulos')]
    public function index(
        #[MapQueryString] ?PaginationRequest $pagination = null,
    ): JsonResponse {
        $pagination ??= new PaginationRequest();
        return $this->json($this->appModuleService->list($pagination));
    }

    #[Route('', methods: ['POST'])]
    #[OA\Post(
        summary: 'Crear un nuevo módulo',
        requestBody: new OA\RequestBody(
            content: new OA\JsonContent(ref: new Model(type: CreateAppModuleRequest::class)),
        ),
    )]
    #[OA\Response(response: 201, description: 'Módulo creado exitosamente')]
    public function create(
        #[MapRequestPayload] CreateAppModuleRequest $request,
    ): JsonResponse {
        return $this->json($this->appModuleService->create($request), Response::HTTP_CREATED);
    }

    #[Route('/{id}', methods: ['PUT'], requirements: ['id' => '\d+'])]
    #[OA\Put(
        summary: 'Actualizar un módulo',
        requestBody: new OA\RequestBody(
            content: new OA\JsonContent(ref: new Model(type: UpdateAppModuleRequest::class)),
        ),
    )]
    #[OA\Response(response: 200, description: 'Módulo actualizado')]
    #[OA\Response(response: 404, description: 'Módulo no encontrado')]
    public function update(
        int $id,
        #[MapRequestPayload] UpdateAppModuleRequest $request,
    ): JsonResponse {
        return $this->json($this->appModuleService->update($id, $request));
    }

    #[Route('/{id}', methods: ['DELETE'], requirements: ['id' => '\d+'])]
    #[OA\Delete(summary: 'Eliminar un módulo')]
    #[OA\Response(response: 204, description: 'Módulo eliminado')]
    #[OA\Response(response: 404, description: 'Módulo no encontrado')]
    public function delete(int $id): JsonResponse
    {
        $this->appModuleService->delete($id);
        return $this->json(null, Response::HTTP_NO_CONTENT);
    }
}
