<?php

declare(strict_types=1);

namespace App\Controller;

use App\DTO\Request\CreateInventoryMovementRequest;
use App\Entity\User;
use App\Pagination\PaginationRequest;
use App\Service\InventoryManager;
use App\Service\InventoryMovementService;
use Nelmio\ApiDocBundle\Attribute\Model;
use OpenApi\Attributes as OA;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\MapQueryString;
use Symfony\Component\HttpKernel\Attribute\MapRequestPayload;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/api/inventario/movements')]
#[OA\Tag(name: 'Inventario - Movimientos')]
final class InventoryMovementController extends AbstractController
{
    public function __construct(
        private readonly InventoryMovementService $movementService,
        private readonly InventoryManager $inventoryManager,
    ) {
    }

    #[Route('', methods: ['GET'])]
    #[OA\Get(summary: 'Listar movimientos de inventario con paginación')]
    #[OA\Response(response: 200, description: 'Lista paginada de movimientos')]
    public function index(
        #[MapQueryString] ?PaginationRequest $pagination,
    ): JsonResponse {
        $pagination ??= new PaginationRequest();
        return $this->json($this->movementService->list($pagination));
    }

    #[Route('/{id}', methods: ['GET'], requirements: ['id' => '\d+'])]
    #[OA\Get(summary: 'Obtener un movimiento de inventario por ID')]
    #[OA\Response(response: 200, description: 'Movimiento encontrado')]
    #[OA\Response(response: 404, description: 'Movimiento no encontrado')]
    public function show(int $id): JsonResponse
    {
        return $this->json($this->movementService->show($id));
    }

    #[Route('', methods: ['POST'])]
    #[OA\Post(
        summary: 'Crear un movimiento manual de inventario',
        requestBody: new OA\RequestBody(
            content: new OA\JsonContent(ref: new Model(type: CreateInventoryMovementRequest::class)),
        ),
    )]
    #[OA\Response(response: 201, description: 'Movimiento creado exitosamente')]
    #[OA\Response(response: 409, description: 'Stock insuficiente')]
    public function create(
        #[MapRequestPayload] CreateInventoryMovementRequest $request,
    ): JsonResponse {
        /** @var User $user */
        $user = $this->getUser();
        return $this->json($this->movementService->createManualMovement($request, $user), Response::HTTP_CREATED);
    }

    #[Route('/kardex/{pacaId}', methods: ['GET'], requirements: ['pacaId' => '\d+'])]
    #[OA\Get(summary: 'Obtener Kardex de una paca')]
    #[OA\Response(response: 200, description: 'Kardex de la paca')]
    public function kardex(
        int $pacaId,
        #[MapQueryString] ?PaginationRequest $pagination,
    ): JsonResponse {
        $pagination ??= new PaginationRequest();
        return $this->json($this->inventoryManager->getKardex($pacaId, $pagination));
    }
}
