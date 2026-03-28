<?php

declare(strict_types=1);

namespace App\Controller;

use App\Entity\FabricType;
use App\Entity\GarmentType;
use App\Entity\GenderCatalog;
use App\Entity\LabelCatalog;
use App\Entity\QualityGrade;
use App\Entity\SeasonCatalog;
use App\Entity\SizeProfile;
use App\Pagination\PaginationRequest;
use App\Service\ProductCatalogService;
use OpenApi\Attributes as OA;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\MapQueryString;
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
    ) {}

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
    #[OA\Post(summary: 'Crear registro de catálogo')]
    public function create(string $type, Request $request): JsonResponse
    {
        $data = json_decode($request->getContent(), true);
        return $this->json(
            $this->service->create(self::CATALOG_MAP[$type], $data['name'] ?? '', $data['description'] ?? null),
            Response::HTTP_CREATED,
        );
    }

    #[Route('/{type}/{id}', methods: ['PUT'], requirements: ['type' => 'labels|qualities|seasons|genders|garment-types|fabric-types|size-profiles', 'id' => '\d+'])]
    #[OA\Put(summary: 'Actualizar registro de catálogo')]
    public function update(string $type, int $id, Request $request): JsonResponse
    {
        $data = json_decode($request->getContent(), true);
        return $this->json($this->service->update(
            self::CATALOG_MAP[$type], $id,
            $data['name'] ?? null, $data['description'] ?? null, $data['active'] ?? null,
        ));
    }

    #[Route('/{type}/{id}', methods: ['DELETE'], requirements: ['type' => 'labels|qualities|seasons|genders|garment-types|fabric-types|size-profiles', 'id' => '\d+'])]
    #[OA\Delete(summary: 'Eliminar registro de catálogo')]
    public function delete(string $type, int $id): JsonResponse
    {
        $this->service->delete(self::CATALOG_MAP[$type], $id);
        return $this->json(null, Response::HTTP_NO_CONTENT);
    }
}
