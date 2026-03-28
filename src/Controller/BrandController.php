<?php

declare(strict_types=1);

namespace App\Controller;

use App\DTO\Request\CreateBrandRequest;
use App\DTO\Request\UpdateBrandRequest;
use App\Pagination\PaginationRequest;
use App\Service\BrandService;
use Nelmio\ApiDocBundle\Attribute\Model;
use OpenApi\Attributes as OA;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\MapQueryString;
use Symfony\Component\HttpKernel\Attribute\MapRequestPayload;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/api/proveedor/brands')]
#[OA\Tag(name: 'Proveedor - Marcas')]
final class BrandController extends AbstractController
{
    public function __construct(private readonly BrandService $service) {}

    #[Route('', methods: ['GET'])]
    #[OA\Get(summary: 'Listar marcas')]
    #[OA\Response(response: 200, description: 'Lista paginada')]
    public function index(#[MapQueryString] ?PaginationRequest $p): JsonResponse
    { return $this->json($this->service->list($p ?? new PaginationRequest())); }

    #[Route('/{id}', methods: ['GET'], requirements: ['id' => '\d+'])]
    #[OA\Get(summary: 'Obtener marca')]
    public function show(int $id): JsonResponse { return $this->json($this->service->show($id)); }

    #[Route('', methods: ['POST'])]
    #[OA\Post(summary: 'Crear marca', requestBody: new OA\RequestBody(content: new OA\JsonContent(ref: new Model(type: CreateBrandRequest::class))))]
    #[OA\Response(response: 201, description: 'Creada')]
    public function create(#[MapRequestPayload] CreateBrandRequest $r): JsonResponse
    { return $this->json($this->service->create($r), Response::HTTP_CREATED); }

    #[Route('/{id}', methods: ['PUT'], requirements: ['id' => '\d+'])]
    #[OA\Put(summary: 'Actualizar marca', requestBody: new OA\RequestBody(content: new OA\JsonContent(ref: new Model(type: UpdateBrandRequest::class))))]
    public function update(int $id, #[MapRequestPayload] UpdateBrandRequest $r): JsonResponse
    { return $this->json($this->service->update($id, $r)); }

    #[Route('/{id}', methods: ['DELETE'], requirements: ['id' => '\d+'])]
    #[OA\Delete(summary: 'Eliminar marca')]
    public function delete(int $id): JsonResponse
    { $this->service->delete($id); return $this->json(null, Response::HTTP_NO_CONTENT); }
}
