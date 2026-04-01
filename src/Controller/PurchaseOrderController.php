<?php

declare(strict_types=1);

namespace App\Controller;

use App\DTO\Request\CreatePurchaseOrderRequest;
use App\DTO\Request\ReceivePurchaseOrderRequest;
use App\DTO\Request\UpdatePurchaseOrderRequest;
use App\Entity\User;
use App\Pagination\PaginationRequest;
use App\Service\PurchaseOrderService;
use Nelmio\ApiDocBundle\Attribute\Model;
use OpenApi\Attributes as OA;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\MapQueryString;
use Symfony\Component\HttpKernel\Attribute\MapRequestPayload;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/api/compras/purchase-orders')]
#[OA\Tag(name: 'Compras - Órdenes de Compra')]
final class PurchaseOrderController extends AbstractController
{
    public function __construct(private readonly PurchaseOrderService $service) {}

    #[Route('', methods: ['GET'])]
    #[OA\Get(summary: 'Listar órdenes de compra')]
    #[OA\Response(response: 200, description: 'Lista paginada')]
    public function index(#[MapQueryString] ?PaginationRequest $p): JsonResponse
    {
        return $this->json($this->service->list($p ?? new PaginationRequest()));
    }

    #[Route('/{id}', methods: ['GET'], requirements: ['id' => '\d+'])]
    #[OA\Get(summary: 'Obtener detalle de orden de compra')]
    public function show(int $id): JsonResponse
    {
        return $this->json($this->service->show($id));
    }

    #[Route('', methods: ['POST'])]
    #[OA\Post(summary: 'Crear orden de compra', requestBody: new OA\RequestBody(content: new OA\JsonContent(ref: new Model(type: CreatePurchaseOrderRequest::class))))]
    #[OA\Response(response: 201, description: 'Creada')]
    public function create(#[MapRequestPayload] CreatePurchaseOrderRequest $r): JsonResponse
    {
        /** @var User $user */
        $user = $this->getUser();
        return $this->json($this->service->create($r, $user), Response::HTTP_CREATED);
    }

    #[Route('/{id}', methods: ['PUT'], requirements: ['id' => '\d+'])]
    #[OA\Put(summary: 'Actualizar orden de compra', requestBody: new OA\RequestBody(content: new OA\JsonContent(ref: new Model(type: UpdatePurchaseOrderRequest::class))))]
    public function update(int $id, #[MapRequestPayload] UpdatePurchaseOrderRequest $r): JsonResponse
    {
        return $this->json($this->service->update($id, $r));
    }

    #[Route('/{id}/receive', methods: ['POST'], requirements: ['id' => '\d+'])]
    #[OA\Post(summary: 'Recibir artículos de orden de compra', requestBody: new OA\RequestBody(content: new OA\JsonContent(ref: new Model(type: ReceivePurchaseOrderRequest::class))))]
    public function receive(int $id, #[MapRequestPayload] ReceivePurchaseOrderRequest $r): JsonResponse
    {
        /** @var User $user */
        $user = $this->getUser();
        return $this->json($this->service->receiveItems($id, $r, $user));
    }

    #[Route('/{id}', methods: ['DELETE'], requirements: ['id' => '\d+'])]
    #[OA\Delete(summary: 'Eliminar orden de compra (solo BORRADOR)')]
    public function delete(int $id): JsonResponse
    {
        $this->service->delete($id);
        return $this->json(null, Response::HTTP_NO_CONTENT);
    }
}
