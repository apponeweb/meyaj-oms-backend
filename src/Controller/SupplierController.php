<?php

declare(strict_types=1);

namespace App\Controller;

use App\DTO\Request\CreateSupplierRequest;
use App\DTO\Request\UpdateSupplierRequest;
use App\Pagination\PaginationRequest;
use App\Entity\User;
use App\Service\SupplierExcelService;
use App\Service\SupplierService;
use Nelmio\ApiDocBundle\Attribute\Model;
use OpenApi\Attributes as OA;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\MapQueryString;
use Symfony\Component\HttpKernel\Attribute\MapRequestPayload;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/api/proveedor/suppliers')]
#[OA\Tag(name: 'Proveedor - Proveedores')]
final class SupplierController extends AbstractController
{
    public function __construct(
        private readonly SupplierService $service,
        private readonly SupplierExcelService $excelService,
    ) {}

    #[Route('', methods: ['GET'])]
    #[OA\Get(summary: 'Listar proveedores')]
    #[OA\Response(response: 200, description: 'Lista paginada')]
    public function index(#[MapQueryString] ?PaginationRequest $p): JsonResponse
    { return $this->json($this->service->list($p ?? new PaginationRequest())); }

    #[Route('/{id}', methods: ['GET'], requirements: ['id' => '\d+'])]
    #[OA\Get(summary: 'Obtener proveedor')]
    public function show(int $id): JsonResponse { return $this->json($this->service->show($id)); }

    #[Route('', methods: ['POST'])]
    #[OA\Post(summary: 'Crear proveedor', requestBody: new OA\RequestBody(content: new OA\JsonContent(ref: new Model(type: CreateSupplierRequest::class))))]
    #[OA\Response(response: 201, description: 'Creado')]
    public function create(#[MapRequestPayload] CreateSupplierRequest $r): JsonResponse
    { return $this->json($this->service->create($r), Response::HTTP_CREATED); }

    #[Route('/{id}', methods: ['PUT'], requirements: ['id' => '\d+'])]
    #[OA\Put(summary: 'Actualizar proveedor', requestBody: new OA\RequestBody(content: new OA\JsonContent(ref: new Model(type: UpdateSupplierRequest::class))))]
    public function update(int $id, #[MapRequestPayload] UpdateSupplierRequest $r): JsonResponse
    { return $this->json($this->service->update($id, $r)); }

    #[Route('/{id}', methods: ['DELETE'], requirements: ['id' => '\d+'])]
    #[OA\Delete(summary: 'Eliminar proveedor')]
    public function delete(int $id): JsonResponse
    { $this->service->delete($id); return $this->json(null, Response::HTTP_NO_CONTENT); }

    #[Route('/{id}/brands', methods: ['PUT'], requirements: ['id' => '\d+'])]
    #[OA\Put(summary: 'Asignar marcas a proveedor')]
    #[OA\RequestBody(content: new OA\JsonContent(properties: [
        new OA\Property(property: 'brandIds', type: 'array', items: new OA\Items(type: 'integer'))
    ]))]
    public function assignBrands(int $id, Request $request): JsonResponse
    {
        $data = json_decode($request->getContent(), true) ?? [];
        return $this->json($this->service->assignBrands($id, $data['brandIds'] ?? []));
    }

    #[Route('/{id}/tags', methods: ['PUT'], requirements: ['id' => '\d+'])]
    #[OA\Put(summary: 'Asignar etiquetas a proveedor')]
    #[OA\RequestBody(content: new OA\JsonContent(properties: [
        new OA\Property(property: 'tagIds', type: 'array', items: new OA\Items(type: 'integer'))
    ]))]
    public function assignTags(int $id, Request $request): JsonResponse
    {
        $data = json_decode($request->getContent(), true) ?? [];
        return $this->json($this->service->assignTags($id, $data['tagIds'] ?? []));
    }

    // ── Excel Export ─────────────────────────────────────────────────

    #[Route('/export', methods: ['GET'])]
    #[OA\Get(summary: 'Exportar proveedores a Excel')]
    public function export(): Response
    {
        return $this->excelService->export();
    }

    // ── Excel Import ─────────────────────────────────────────────────

    #[Route('/import/upload', methods: ['POST'])]
    #[OA\Post(summary: 'Subir archivo Excel para importación')]
    public function importUpload(Request $request): JsonResponse
    {
        $file = $request->files->get('file');
        if (!$file) return $this->json(['error' => 'No se proporcionó archivo'], Response::HTTP_BAD_REQUEST);

        /** @var User $user */
        $user = $this->getUser();
        $log = $this->excelService->uploadImportFile($file, $file->getClientOriginalName(), $user);

        return $this->json([
            'importId' => $log->getId(),
            'totalRows' => $log->getTotalRows(),
            'filename' => $log->getOriginalFilename(),
        ], Response::HTTP_CREATED);
    }

    #[Route('/import/{id}/process', methods: ['POST'], requirements: ['id' => '\d+'])]
    #[OA\Post(summary: 'Procesar importación de proveedores')]
    public function importProcess(int $id): JsonResponse
    {
        return $this->json($this->excelService->processImport($id));
    }

    #[Route('/import/{id}/status', methods: ['GET'], requirements: ['id' => '\d+'])]
    #[OA\Get(summary: 'Consultar estado de importación')]
    public function importStatus(int $id): JsonResponse
    {
        return $this->json($this->excelService->getImportStatus($id));
    }

    #[Route('/import/history', methods: ['GET'])]
    #[OA\Get(summary: 'Obtener historial de importaciones')]
    public function importHistory(): JsonResponse
    {
        return $this->json($this->excelService->getHistory());
    }
}
