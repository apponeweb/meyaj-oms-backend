<?php

declare(strict_types=1);

namespace App\Controller;

use App\DTO\Request\CreateProductCatalogRequest;
use App\DTO\Request\UpdateProductCatalogRequest;
use App\Entity\FabricType;
use App\Entity\GarmentType;
use App\Entity\GenderCatalog;
use App\Entity\LabelCatalog;
use App\Entity\QualityGrade;
use App\Entity\SeasonCatalog;
use App\Entity\SizeProfile;
use App\Pagination\PaginationRequest;
use App\Service\ProductCatalogService;
use Nelmio\ApiDocBundle\Attribute\Model;
use OpenApi\Attributes as OA;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\MapQueryString;
use Symfony\Component\HttpKernel\Attribute\MapRequestPayload;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/api/productos/catalogs')]
#[OA\Tag(name: 'Productos - Catálogos')]
final class ProductCatalogController extends AbstractController
{
    private const CATALOG_MAP = [
        'labels' => LabelCatalog::class,
        'qualities' => QualityGrade::class,
        'seasons' => SeasonCatalog::class,
        'genders' => GenderCatalog::class,
        'garment-types' => GarmentType::class,
        'fabric-types' => FabricType::class,
        'size-profiles' => SizeProfile::class,
    ];

    public function __construct(
        private readonly ProductCatalogService $service,
    ) {
    }

    #[Route('/{type}', methods: ['GET'], requirements: ['type' => 'labels|qualities|seasons|genders|garment-types|fabric-types|size-profiles'])]
    #[OA\Get(summary: 'Listar registros de un catálogo de producto')]
    public function index(string $type, #[MapQueryString] ?PaginationRequest $p): JsonResponse
    {
        return $this->json($this->service->list(self::CATALOG_MAP[$type], $p ?? new PaginationRequest()));
    }

    #[Route('/{type}/{id}', methods: ['GET'], requirements: ['type' => 'labels|qualities|seasons|genders|garment-types|fabric-types|size-profiles', 'id' => '\d+'])]
    #[OA\Get(summary: 'Obtener registro de catálogo')]
    public function show(string $type, int $id): JsonResponse
    {
        return $this->json($this->service->show(self::CATALOG_MAP[$type], $id));
    }

    #[Route('/{type}', methods: ['POST'], requirements: ['type' => 'labels|qualities|seasons|genders|garment-types|fabric-types|size-profiles'])]
    #[OA\Post(
        summary: 'Crear registro de catálogo',
        requestBody: new OA\RequestBody(
            content: new OA\JsonContent(ref: new Model(type: CreateProductCatalogRequest::class)),
        ),
    )]
    #[OA\Response(response: 201, description: 'Registro creado exitosamente')]
    public function create(
        string $type,
        #[MapRequestPayload] CreateProductCatalogRequest $request,
    ): JsonResponse {
        return $this->json(
            $this->service->create(self::CATALOG_MAP[$type], $request->name, $request->description),
            Response::HTTP_CREATED,
        );
    }

    #[Route('/{type}/{id}', methods: ['PUT'], requirements: ['type' => 'labels|qualities|seasons|genders|garment-types|fabric-types|size-profiles', 'id' => '\d+'])]
    #[OA\Put(
        summary: 'Actualizar registro de catálogo',
        requestBody: new OA\RequestBody(
            content: new OA\JsonContent(ref: new Model(type: UpdateProductCatalogRequest::class)),
        ),
    )]
    #[OA\Response(response: 200, description: 'Registro actualizado')]
    #[OA\Response(response: 404, description: 'Registro no encontrado')]
    public function update(
        string $type,
        int $id,
        #[MapRequestPayload] UpdateProductCatalogRequest $request,
    ): JsonResponse {
        return $this->json($this->service->update(
            self::CATALOG_MAP[$type],
            $id,
            $request->name,
            $request->description,
            $request->active,
        ));
    }

    #[Route('/{type}/{id}', methods: ['DELETE'], requirements: ['type' => 'labels|qualities|seasons|genders|garment-types|fabric-types|size-profiles', 'id' => '\d+'])]
    #[OA\Delete(summary: 'Eliminar registro de catálogo')]
    #[OA\Response(response: 204, description: 'Registro eliminado')]
    #[OA\Response(response: 404, description: 'Registro no encontrado')]
    public function delete(string $type, int $id): JsonResponse
    {
        $this->service->delete(self::CATALOG_MAP[$type], $id);
        return $this->json(null, Response::HTTP_NO_CONTENT);
    }
}
