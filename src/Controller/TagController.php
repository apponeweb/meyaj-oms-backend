<?php

declare(strict_types=1);

namespace App\Controller;

use App\DTO\Request\CreateTagRequest;
use App\DTO\Request\UpdateTagRequest;
use App\Service\TagService;
use Nelmio\ApiDocBundle\Attribute\Model;
use OpenApi\Attributes as OA;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\MapRequestPayload;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/api/tags')]
#[OA\Tag(name: 'Etiquetas')]
final class TagController extends AbstractController
{
    public function __construct(private readonly TagService $service) {}

    #[Route('', methods: ['GET'])]
    #[OA\Get(summary: 'Listar etiquetas')]
    #[OA\Response(response: 200, description: 'Lista de etiquetas')]
    public function index(Request $request): JsonResponse
    {
        $active = $request->query->get('active');
        $activeFilter = $active !== null ? filter_var($active, FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE) : null;
        return $this->json($this->service->list($activeFilter));
    }

    #[Route('/{id}', methods: ['GET'], requirements: ['id' => '\d+'])]
    #[OA\Get(summary: 'Obtener etiqueta')]
    public function show(int $id): JsonResponse { return $this->json($this->service->show($id)); }

    #[Route('', methods: ['POST'])]
    #[OA\Post(summary: 'Crear etiqueta', requestBody: new OA\RequestBody(content: new OA\JsonContent(ref: new Model(type: CreateTagRequest::class))))]
    #[OA\Response(response: 201, description: 'Creada')]
    public function create(#[MapRequestPayload] CreateTagRequest $r): JsonResponse
    { return $this->json($this->service->create($r), Response::HTTP_CREATED); }

    #[Route('/{id}', methods: ['PUT'], requirements: ['id' => '\d+'])]
    #[OA\Put(summary: 'Actualizar etiqueta', requestBody: new OA\RequestBody(content: new OA\JsonContent(ref: new Model(type: UpdateTagRequest::class))))]
    public function update(int $id, #[MapRequestPayload] UpdateTagRequest $r): JsonResponse
    { return $this->json($this->service->update($id, $r)); }

    #[Route('/{id}', methods: ['DELETE'], requirements: ['id' => '\d+'])]
    #[OA\Delete(summary: 'Eliminar etiqueta')]
    public function delete(int $id): JsonResponse
    { $this->service->delete($id); return $this->json(null, Response::HTTP_NO_CONTENT); }
}
