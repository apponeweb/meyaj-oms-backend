<?php

declare(strict_types=1);

namespace App\Controller;

use App\DTO\Request\CreateBrandRequest;
use App\DTO\Request\UpdateBrandRequest;
use App\Pagination\PaginationRequest;
use App\Entity\User;
use App\Service\BrandExcelService;
use App\Service\BrandService;
use App\Service\OpenAIService;
use Nelmio\ApiDocBundle\Attribute\Model;
use OpenApi\Attributes as OA;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\MapQueryString;
use Symfony\Component\HttpKernel\Attribute\MapRequestPayload;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/api/proveedor/brands')]
#[OA\Tag(name: 'Proveedor - Marcas')]
final class BrandController extends AbstractController
{
    public function __construct(
        private readonly BrandService $service,
        private readonly BrandExcelService $excelService,
        private readonly OpenAIService $openAIService,
    ) {}

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

    // ── Excel Export ─────────────────────────────────────────────────

    #[Route('/export', methods: ['GET'])]
    #[OA\Get(summary: 'Exportar marcas a Excel')]
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
    #[OA\Post(summary: 'Procesar importación de marcas')]
    public function importProcess(int $id): JsonResponse
    {
        return $this->json($this->excelService->processImport($id), 200, [], ['groups' => ['import_log:read']]);
    }

    #[Route('/import/{id}/status', methods: ['GET'], requirements: ['id' => '\d+'])]
    #[OA\Get(summary: 'Consultar estado de importación')]
    public function importStatus(int $id): JsonResponse
    {
        return $this->json($this->excelService->getImportStatus($id), 200, [], ['groups' => ['import_log:read']]);
    }

    #[Route('/import/history', methods: ['GET'])]
    #[OA\Get(summary: 'Obtener historial de importaciones')]
    public function importHistory(): JsonResponse
    {
        return $this->json($this->excelService->getHistory(), 200, [], ['groups' => ['import_log:read']]);
    }

    #[Route('/generate-description', methods: ['POST'])]
    #[OA\Post(summary: 'Generar descripción con IA')]
    public function generateDescription(Request $request): JsonResponse
    {
        try {
            $data = json_decode($request->getContent(), true);
            
            if (!$data || !isset($data['prompt'])) {
                return $this->json(['error' => 'El prompt es requerido'], 400);
            }

            $prompt = $data['prompt'];
            
            // Extraer el nombre de la marca del prompt
            $brandName = 'la marca';
            if (preg_match('/marca "([^"]+)"/', $prompt, $matches)) {
                $brandName = $matches[1];
            }

            $description = $this->openAIService->generateBrandDescription($brandName);

            return $this->json(['description' => $description]);
        } catch (\Exception $e) {
            return $this->json(['error' => 'Error al generar descripción: ' . $e->getMessage()], 500);
        }
    }
}
