<?php

declare(strict_types=1);

namespace App\Controller;

use App\DTO\Request\CreateInventoryCountRequest;
use App\Entity\User;
use App\Pagination\PaginationRequest;
use App\Service\InventoryCountService;
use OpenApi\Attributes as OA;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\MapQueryString;
use Symfony\Component\HttpKernel\Attribute\MapRequestPayload;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/api/inventario/counts')]
#[OA\Tag(name: 'Inventario - Conteos Físicos')]
final class InventoryCountController extends AbstractController
{
    public function __construct(
        private readonly InventoryCountService $countService,
    ) {
    }

    #[Route('', methods: ['GET'])]
    #[OA\Get(summary: 'Listar conteos de inventario con paginación')]
    #[OA\Response(response: 200, description: 'Lista paginada de conteos')]
    public function index(
        #[MapQueryString] ?PaginationRequest $pagination,
    ): JsonResponse {
        $pagination ??= new PaginationRequest();
        return $this->json($this->countService->list($pagination));
    }

    #[Route('/{id}', methods: ['GET'], requirements: ['id' => '\d+'])]
    #[OA\Get(summary: 'Obtener un conteo de inventario por ID con sus detalles')]
    #[OA\Response(response: 200, description: 'Conteo encontrado')]
    #[OA\Response(response: 404, description: 'Conteo no encontrado')]
    public function show(int $id): JsonResponse
    {
        return $this->json($this->countService->show($id));
    }

    #[Route('', methods: ['POST'])]
    #[OA\Post(summary: 'Crear un nuevo conteo de inventario')]
    #[OA\Response(response: 201, description: 'Conteo creado exitosamente')]
    public function create(
        #[MapRequestPayload] CreateInventoryCountRequest $request,
    ): JsonResponse {
        /** @var User $user */
        $user = $this->getUser();
        return $this->json($this->countService->create($request, $user), Response::HTTP_CREATED);
    }

    #[Route('/{id}/start', methods: ['POST'], requirements: ['id' => '\d+'])]
    #[OA\Post(summary: 'Iniciar un conteo (poblar detalles desde pacas activas)')]
    #[OA\Response(response: 200, description: 'Conteo iniciado')]
    #[OA\Response(response: 409, description: 'El conteo no está en estado DRAFT')]
    public function start(int $id): JsonResponse
    {
        return $this->json($this->countService->startCount($id));
    }

    #[Route('/{id}/details/{detailId}', methods: ['PUT'], requirements: ['id' => '\d+', 'detailId' => '\d+'])]
    #[OA\Put(summary: 'Actualizar un detalle del conteo')]
    #[OA\Response(response: 200, description: 'Detalle actualizado')]
    #[OA\Response(response: 409, description: 'El conteo no está en progreso')]
    public function updateDetail(int $id, int $detailId, Request $request): JsonResponse
    {
        $payload = json_decode($request->getContent(), true, 512, \JSON_THROW_ON_ERROR);
        $countedQty = isset($payload['countedQty']) ? (int) $payload['countedQty'] : null;
        $notes = $payload['notes'] ?? null;
        $scannedSerials = isset($payload['scannedSerials']) && is_array($payload['scannedSerials'])
            ? $payload['scannedSerials']
            : null;

        return $this->json($this->countService->updateDetail($id, $detailId, $countedQty, $notes, $scannedSerials));
    }

    #[Route('/{id}/finalize', methods: ['POST'], requirements: ['id' => '\d+'])]
    #[OA\Post(summary: 'Finalizar un conteo y aplicar ajustes')]
    #[OA\Response(response: 200, description: 'Conteo finalizado')]
    #[OA\Response(response: 400, description: 'No todos los detalles han sido contados')]
    #[OA\Response(response: 409, description: 'El conteo no está en progreso')]
    public function finalize(int $id): JsonResponse
    {
        /** @var User $user */
        $user = $this->getUser();
        return $this->json($this->countService->finalizeCount($id, $user));
    }

    #[Route('/{id}/start-recount', methods: ['POST'], requirements: ['id' => '\d+'])]
    #[OA\Post(summary: 'Iniciar reconteo de un conteo completado con discrepancias')]
    #[OA\Response(response: 200, description: 'Reconteo iniciado')]
    #[OA\Response(response: 400, description: 'No hay discrepancias para recontar')]
    #[OA\Response(response: 409, description: 'El conteo no está en estado COMPLETED')]
    public function startRecount(int $id): JsonResponse
    {
        return $this->json($this->countService->startRecount($id));
    }

    #[Route('/{id}/finalize-recount', methods: ['POST'], requirements: ['id' => '\d+'])]
    #[OA\Post(summary: 'Finalizar reconteo y aplicar ajustes')]
    #[OA\Response(response: 200, description: 'Reconteo finalizado')]
    #[OA\Response(response: 400, description: 'No todos los detalles ajustados han sido recontados')]
    #[OA\Response(response: 409, description: 'El conteo no está en estado RECOUNT')]
    public function finalizeRecount(int $id): JsonResponse
    {
        /** @var User $user */
        $user = $this->getUser();
        return $this->json($this->countService->finalizeRecount($id, $user));
    }

    #[Route('/{id}', methods: ['DELETE'], requirements: ['id' => '\d+'])]
    #[OA\Delete(summary: 'Eliminar un conteo en estado DRAFT')]
    #[OA\Response(response: 204, description: 'Conteo eliminado')]
    #[OA\Response(response: 409, description: 'Solo se pueden eliminar conteos en estado DRAFT')]
    public function delete(int $id): JsonResponse
    {
        $this->countService->delete($id);
        return $this->json(null, Response::HTTP_NO_CONTENT);
    }
}
