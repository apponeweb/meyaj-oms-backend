<?php

declare(strict_types=1);

namespace App\Controller;

use App\DTO\Request\CreateCustomerRequest;
use App\DTO\Request\UpdateCustomerRequest;
use App\Pagination\PaginationRequest;
use App\Service\CustomerService;
use App\Service\CustomerExcelService;
use Nelmio\ApiDocBundle\Attribute\Model;
use OpenApi\Attributes as OA;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\MapQueryString;
use Symfony\Component\HttpKernel\Attribute\MapRequestPayload;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/api/customers')]
#[OA\Tag(name: 'Clientes')]
final class CustomerController extends AbstractController
{
    public function __construct(
        private readonly CustomerService $customerService,
        private readonly CustomerExcelService $excelService,
    ) {
    }

    #[Route('', methods: ['GET'])]
    #[OA\Get(summary: 'Listar clientes con paginación')]
    #[OA\Response(response: 200, description: 'Lista paginada de clientes')]
    public function index(
        #[MapQueryString] ?PaginationRequest $pagination = null,
    ): JsonResponse {
        $pagination ??= new PaginationRequest();
        return $this->json($this->customerService->list($pagination));
    }

    #[Route('/search', methods: ['GET'])]
    #[OA\Get(summary: 'Buscar clientes por nombre o teléfono')]
    #[OA\Parameter(name: 'q', in: 'query', schema: new OA\Schema(type: 'string'))]
    #[OA\Response(response: 200, description: 'Resultados de búsqueda')]
    public function search(Request $request): JsonResponse
    {
        $query = $request->query->get('q', '');

        if (strlen($query) < 2) {
            return $this->json(['data' => []]);
        }

        return $this->json(['data' => $this->customerService->search($query)]);
    }

    #[Route('/{id}', methods: ['GET'], requirements: ['id' => '\d+'])]
    #[OA\Get(summary: 'Obtener un cliente por ID')]
    #[OA\Response(response: 200, description: 'Cliente encontrado')]
    #[OA\Response(response: 404, description: 'Cliente no encontrado')]
    public function show(int $id): JsonResponse
    {
        return $this->json($this->customerService->show($id));
    }

    #[Route('', methods: ['POST'])]
    #[OA\Post(
        summary: 'Crear un nuevo cliente',
        requestBody: new OA\RequestBody(
            content: new OA\JsonContent(ref: new Model(type: CreateCustomerRequest::class)),
        ),
    )]
    #[OA\Response(response: 201, description: 'Cliente creado exitosamente')]
    public function create(
        #[MapRequestPayload] CreateCustomerRequest $request,
    ): JsonResponse {
        return $this->json($this->customerService->create($request), Response::HTTP_CREATED);
    }

    #[Route('/{id}', methods: ['PUT'], requirements: ['id' => '\d+'])]
    #[OA\Put(
        summary: 'Actualizar un cliente',
        requestBody: new OA\RequestBody(
            content: new OA\JsonContent(ref: new Model(type: UpdateCustomerRequest::class)),
        ),
    )]
    #[OA\Response(response: 200, description: 'Cliente actualizado')]
    #[OA\Response(response: 404, description: 'Cliente no encontrado')]
    public function update(
        int $id,
        #[MapRequestPayload] UpdateCustomerRequest $request,
    ): JsonResponse {
        return $this->json($this->customerService->update($id, $request));
    }

    #[Route('/{id}', methods: ['DELETE'], requirements: ['id' => '\d+'])]
    #[OA\Delete(summary: 'Eliminar un cliente (soft delete)')]
    #[OA\Response(response: 204, description: 'Cliente eliminado')]
    #[OA\Response(response: 404, description: 'Cliente no encontrado')]
    public function delete(int $id): JsonResponse
    {
        $this->customerService->delete($id);
        return $this->json(null, Response::HTTP_NO_CONTENT);
    }

    #[Route('/export', methods: ['GET'])]
    public function export(): Response
    {
        return $this->excelService->export();
    }

    #[Route('/import/upload', methods: ['POST'])]
    public function uploadImport(Request $request): JsonResponse
    {
        $file = $request->files->get('file');
        if (!$file) return $this->json(['error' => 'No file uploaded'], 400);
        $log = $this->excelService->uploadImportFile($file, $file->getClientOriginalName(), $this->getUser());
        return $this->json(['importId' => $log->getId()]);
    }

    #[Route('/import/{id}/process', methods: ['POST'])]
    public function processImport(int $id): JsonResponse
    {
        return $this->json($this->excelService->processImport($id));
    }

    #[Route('/import/{id}/status', methods: ['GET'])]
    public function importStatus(int $id): JsonResponse
    {
        return $this->json($this->excelService->getImportStatus($id));
    }

    #[Route('/import/history', methods: ['GET'])]
    public function importHistory(): JsonResponse
    {
        return $this->json($this->excelService->getHistory());
    }
}
