<?php

declare(strict_types=1);

namespace App\Controller;

use App\DTO\Request\ChangeStatusRequest;
use App\DTO\Request\CreateSalesOrderRequest;
use App\Entity\User;
use App\Pagination\PaginationRequest;
use App\Service\SalesOrderService;
use Nelmio\ApiDocBundle\Attribute\Model;
use OpenApi\Attributes as OA;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\MapQueryString;
use Symfony\Component\HttpKernel\Attribute\MapRequestPayload;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/api/pedidos/sales-orders')]
#[OA\Tag(name: 'Pedidos - Pedidos de Venta')]
final class SalesOrderController extends AbstractController
{
    public function __construct(private readonly SalesOrderService $service) {}

    #[Route('', methods: ['GET'])]
    #[OA\Get(summary: 'Listar pedidos de venta')]
    #[OA\Response(response: 200, description: 'Lista paginada')]
    public function index(#[MapQueryString] ?PaginationRequest $p): JsonResponse
    {
        return $this->json($this->service->list($p ?? new PaginationRequest()));
    }

    #[Route('/{id}', methods: ['GET'], requirements: ['id' => '\d+'])]
    #[OA\Get(summary: 'Obtener detalle de pedido de venta')]
    #[OA\Response(response: 200, description: 'Detalle del pedido')]
    #[OA\Response(response: 404, description: 'Pedido no encontrado')]
    public function show(int $id): JsonResponse
    {
        return $this->json($this->service->show($id));
    }

    #[Route('', methods: ['POST'])]
    #[OA\Post(
        summary: 'Crear un nuevo pedido de venta',
        requestBody: new OA\RequestBody(
            content: new OA\JsonContent(ref: new Model(type: CreateSalesOrderRequest::class)),
        ),
    )]
    #[OA\Response(response: 201, description: 'Pedido creado')]
    public function create(#[MapRequestPayload] CreateSalesOrderRequest $r): JsonResponse
    {
        /** @var User $user */
        $user = $this->getUser();
        return $this->json($this->service->create($r, $user), Response::HTTP_CREATED);
    }

    #[Route('/{id}/status', methods: ['POST'], requirements: ['id' => '\d+'])]
    #[OA\Post(
        summary: 'Cambiar estado de pedido de venta',
        requestBody: new OA\RequestBody(
            content: new OA\JsonContent(ref: new Model(type: ChangeStatusRequest::class)),
        ),
    )]
    #[OA\Response(response: 200, description: 'Estado actualizado')]
    #[OA\Response(response: 400, description: 'Transición no permitida')]
    #[OA\Response(response: 404, description: 'Pedido no encontrado')]
    public function changeStatus(int $id, #[MapRequestPayload] ChangeStatusRequest $r): JsonResponse
    {
        /** @var User $user */
        $user = $this->getUser();
        return $this->json($this->service->changeStatus($id, $r, $user));
    }

    #[Route('/{id}/payment-status', methods: ['POST'], requirements: ['id' => '\d+'])]
    #[OA\Post(
        summary: 'Cambiar estado de pago del pedido',
        requestBody: new OA\RequestBody(
            content: new OA\JsonContent(ref: new Model(type: ChangeStatusRequest::class)),
        ),
    )]
    #[OA\Response(response: 200, description: 'Estado de pago actualizado')]
    public function changePaymentStatus(int $id, #[MapRequestPayload] ChangeStatusRequest $r): JsonResponse
    {
        /** @var User $user */
        $user = $this->getUser();
        return $this->json($this->service->changePaymentStatus($id, $r, $user));
    }

    #[Route('/{id}', methods: ['DELETE'], requirements: ['id' => '\d+'])]
    #[OA\Delete(summary: 'Eliminar pedido de venta')]
    #[OA\Response(response: 204, description: 'Pedido eliminado')]
    #[OA\Response(response: 400, description: 'Solo se pueden eliminar pedidos pendientes')]
    #[OA\Response(response: 404, description: 'Pedido no encontrado')]
    public function delete(int $id): JsonResponse
    {
        $this->service->delete($id);
        return $this->json(null, Response::HTTP_NO_CONTENT);
    }
}
