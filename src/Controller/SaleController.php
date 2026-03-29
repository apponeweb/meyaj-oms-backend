<?php

declare(strict_types=1);

namespace App\Controller;

use App\DTO\Request\CreateSaleRequest;
use App\Pagination\PaginationRequest;
use App\Service\SaleService;
use Nelmio\ApiDocBundle\Attribute\Model;
use OpenApi\Attributes as OA;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\MapQueryString;
use Symfony\Component\HttpKernel\Attribute\MapRequestPayload;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/api/sales')]
#[OA\Tag(name: 'Ventas')]
final class SaleController extends AbstractController
{
    public function __construct(
        private readonly SaleService $saleService,
    ) {
    }

    #[Route('', methods: ['GET'])]
    #[OA\Get(summary: 'Listar ventas con paginación')]
    #[OA\Parameter(name: 'status', in: 'query', schema: new OA\Schema(type: 'string'))]
    #[OA\Parameter(name: 'start_date', in: 'query', schema: new OA\Schema(type: 'string', format: 'date'))]
    #[OA\Parameter(name: 'end_date', in: 'query', schema: new OA\Schema(type: 'string', format: 'date'))]
    #[OA\Response(response: 200, description: 'Lista paginada de ventas')]
    public function index(
        Request $httpRequest,
        #[MapQueryString] ?PaginationRequest $pagination = null,
    ): JsonResponse {
        $pagination ??= new PaginationRequest();
        return $this->json($this->saleService->list(
            $pagination,
            $httpRequest->query->get('status'),
            $httpRequest->query->get('start_date'),
            $httpRequest->query->get('end_date'),
        ));
    }

    #[Route('', methods: ['POST'])]
    #[OA\Post(
        summary: 'Crear una nueva venta',
        requestBody: new OA\RequestBody(
            content: new OA\JsonContent(ref: new Model(type: CreateSaleRequest::class)),
        ),
    )]
    #[OA\Response(response: 201, description: 'Venta creada exitosamente')]
    public function create(
        #[MapRequestPayload] CreateSaleRequest $request,
    ): JsonResponse {
        return $this->json($this->saleService->createSale($request), Response::HTTP_CREATED);
    }

    #[Route('/{id}', methods: ['GET'], requirements: ['id' => '\d+'])]
    #[OA\Get(summary: 'Obtener detalle de una venta')]
    #[OA\Response(response: 200, description: 'Detalle de la venta')]
    #[OA\Response(response: 404, description: 'Venta no encontrada')]
    public function show(int $id): JsonResponse
    {
        return $this->json($this->saleService->show($id));
    }

    #[Route('/{id}/cancel', methods: ['POST'], requirements: ['id' => '\d+'])]
    #[OA\Post(summary: 'Cancelar una venta')]
    #[OA\Response(response: 200, description: 'Venta cancelada')]
    #[OA\Response(response: 404, description: 'Venta no encontrada')]
    public function cancel(int $id): JsonResponse
    {
        return $this->json($this->saleService->cancelSale($id));
    }

    #[Route('/reports/daily', methods: ['GET'])]
    #[OA\Get(summary: 'Reporte de ventas diario')]
    #[OA\Parameter(name: 'date', in: 'query', schema: new OA\Schema(type: 'string', format: 'date'))]
    #[OA\Response(response: 200, description: 'Reporte diario')]
    public function dailyReport(Request $request): JsonResponse
    {
        return $this->json($this->saleService->dailyReport($request->query->get('date')));
    }

    #[Route('/reports/monthly', methods: ['GET'])]
    #[OA\Get(summary: 'Reporte de ventas mensual')]
    #[OA\Parameter(name: 'date', in: 'query', schema: new OA\Schema(type: 'string', format: 'date'))]
    #[OA\Response(response: 200, description: 'Reporte mensual')]
    public function monthlyReport(Request $request): JsonResponse
    {
        return $this->json($this->saleService->monthlyReport($request->query->get('date')));
    }
}
