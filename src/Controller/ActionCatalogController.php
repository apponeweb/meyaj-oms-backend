<?php

declare(strict_types=1);

namespace App\Controller;

use App\DTO\Request\CreateActionCatalogRequest;
use App\DTO\Request\UpdateActionCatalogRequest;
use App\Pagination\PaginationRequest;
use App\Service\ActionCatalogService;
use Nelmio\ApiDocBundle\Attribute\Model;
use OpenApi\Attributes as OA;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\MapQueryString;
use Symfony\Component\HttpKernel\Attribute\MapRequestPayload;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/api/seguridad/actions')]
#[OA\Tag(name: 'Seguridad - Acciones')]
final class ActionCatalogController extends AbstractController
{
    public function __construct(
        private readonly ActionCatalogService $actionCatalogService,
    ) {
    }

    #[Route('', methods: ['GET'])]
    #[OA\Get(summary: 'Listar acciones con paginación')]
    #[OA\Response(response: 200, description: 'Lista paginada de acciones')]
    public function index(
        #[MapQueryString] ?PaginationRequest $pagination = null,
    ): JsonResponse {
        $pagination ??= new PaginationRequest();
        return $this->json($this->actionCatalogService->list($pagination));
    }

    #[Route('', methods: ['POST'])]
    #[OA\Post(
        summary: 'Crear una nueva acción',
        requestBody: new OA\RequestBody(
            content: new OA\JsonContent(ref: new Model(type: CreateActionCatalogRequest::class)),
        ),
    )]
    #[OA\Response(response: 201, description: 'Acción creada exitosamente')]
    public function create(
        #[MapRequestPayload] CreateActionCatalogRequest $request,
    ): JsonResponse {
        return $this->json($this->actionCatalogService->create($request), Response::HTTP_CREATED);
    }

    #[Route('/{id}', methods: ['PUT'], requirements: ['id' => '\d+'])]
    #[OA\Put(
        summary: 'Actualizar una acción',
        requestBody: new OA\RequestBody(
            content: new OA\JsonContent(ref: new Model(type: UpdateActionCatalogRequest::class)),
        ),
    )]
    #[OA\Response(response: 200, description: 'Acción actualizada')]
    #[OA\Response(response: 404, description: 'Acción no encontrada')]
    public function update(
        int $id,
        #[MapRequestPayload] UpdateActionCatalogRequest $request,
    ): JsonResponse {
        return $this->json($this->actionCatalogService->update($id, $request));
    }

    #[Route('/{id}', methods: ['DELETE'], requirements: ['id' => '\d+'])]
    #[OA\Delete(summary: 'Eliminar una acción')]
    #[OA\Response(response: 204, description: 'Acción eliminada')]
    #[OA\Response(response: 404, description: 'Acción no encontrada')]
    public function delete(int $id): JsonResponse
    {
        $this->actionCatalogService->delete($id);
        return $this->json(null, Response::HTTP_NO_CONTENT);
    }
}
