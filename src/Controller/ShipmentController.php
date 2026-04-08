<?php

declare(strict_types=1);

namespace App\Controller;

use App\DTO\Request\CreateShipmentOrderRequest;
use App\DTO\Request\ScanShipmentItemRequest;
use App\DTO\Request\ShipShipmentOrderRequest;
use App\Entity\User;
use App\Pagination\PaginationRequest;
use App\Service\ShipmentService;
use Nelmio\ApiDocBundle\Attribute\Model;
use OpenApi\Attributes as OA;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\MapQueryString;
use Symfony\Component\HttpKernel\Attribute\MapRequestPayload;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/api/despacho/shipments')]
#[OA\Tag(name: 'Despacho - Envíos')]
final class ShipmentController extends AbstractController
{
    public function __construct(private readonly ShipmentService $service) {}

    #[Route('', methods: ['GET'])]
    #[OA\Get(summary: 'Listar envíos')]
    #[OA\Response(response: 200, description: 'Lista paginada')]
    public function index(#[MapQueryString] ?PaginationRequest $p): JsonResponse
    {
        return $this->json($this->service->list($p ?? new PaginationRequest()));
    }

    #[Route('/{id}', methods: ['GET'], requirements: ['id' => '\d+'])]
    #[OA\Get(summary: 'Obtener detalle de envío')]
    #[OA\Response(response: 200, description: 'Detalle del envío')]
    #[OA\Response(response: 404, description: 'Envío no encontrado')]
    public function show(int $id): JsonResponse
    {
        return $this->json($this->service->show($id));
    }

    #[Route('', methods: ['POST'])]
    #[OA\Post(
        summary: 'Crear un nuevo envío',
        requestBody: new OA\RequestBody(
            content: new OA\JsonContent(ref: new Model(type: CreateShipmentOrderRequest::class)),
        ),
    )]
    #[OA\Response(response: 201, description: 'Envío creado')]
    public function create(#[MapRequestPayload] CreateShipmentOrderRequest $r): JsonResponse
    {
        /** @var User $user */
        $user = $this->getUser();
        return $this->json($this->service->create($r, $user), Response::HTTP_CREATED);
    }

    #[Route('/{id}/scan', methods: ['POST'], requirements: ['id' => '\d+'])]
    #[OA\Post(
        summary: 'Escanear unidad en envío',
        requestBody: new OA\RequestBody(
            content: new OA\JsonContent(ref: new Model(type: ScanShipmentItemRequest::class)),
        ),
    )]
    #[OA\Response(response: 200, description: 'Unidad escaneada')]
    #[OA\Response(response: 400, description: 'Estado inválido')]
    #[OA\Response(response: 404, description: 'Envío o unidad no encontrada')]
    #[OA\Response(response: 409, description: 'Unidad ya escaneada o estado inválido')]
    public function scan(int $id, #[MapRequestPayload] ScanShipmentItemRequest $r): JsonResponse
    {
        /** @var User $user */
        $user = $this->getUser();
        return $this->json($this->service->scanItem($id, $r, $user));
    }

    #[Route('/{id}/ship', methods: ['POST'], requirements: ['id' => '\d+'])]
    #[OA\Post(
        summary: 'Marcar envío como enviado',
        requestBody: new OA\RequestBody(
            content: new OA\JsonContent(ref: new Model(type: ShipShipmentOrderRequest::class)),
        ),
    )]
    #[OA\Response(response: 200, description: 'Envío marcado como enviado')]
    #[OA\Response(response: 400, description: 'Estado inválido')]
    public function ship(int $id, #[MapRequestPayload] ShipShipmentOrderRequest $r): JsonResponse
    {
        /** @var User $user */
        $user = $this->getUser();
        return $this->json($this->service->ship($id, $r, $user));
    }

    #[Route('/{id}/deliver', methods: ['POST'], requirements: ['id' => '\d+'])]
    #[OA\Post(summary: 'Marcar envío como entregado')]
    #[OA\Response(response: 200, description: 'Envío marcado como entregado')]
    #[OA\Response(response: 400, description: 'Estado inválido')]
    public function deliver(int $id): JsonResponse
    {
        /** @var User $user */
        $user = $this->getUser();
        return $this->json($this->service->markDelivered($id, $user));
    }

    #[Route('/{id}/cancel', methods: ['POST'], requirements: ['id' => '\d+'])]
    #[OA\Post(summary: 'Cancelar envío')]
    #[OA\Response(response: 200, description: 'Envío cancelado')]
    #[OA\Response(response: 400, description: 'No se puede cancelar')]
    public function cancel(int $id): JsonResponse
    {
        /** @var User $user */
        $user = $this->getUser();
        return $this->json($this->service->cancel($id, $user));
    }
}
