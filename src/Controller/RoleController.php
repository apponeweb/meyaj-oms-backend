<?php

declare(strict_types=1);

namespace App\Controller;

use App\DTO\Request\CreateRoleRequest;
use App\DTO\Request\UpdateRolePermissionsRequest;
use App\DTO\Request\UpdateRoleRequest;
use App\Pagination\PaginationRequest;
use App\Service\RoleService;
use Nelmio\ApiDocBundle\Attribute\Model;
use OpenApi\Attributes as OA;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\MapQueryString;
use Symfony\Component\HttpKernel\Attribute\MapRequestPayload;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/api/seguridad/roles')]
#[OA\Tag(name: 'Seguridad - Roles')]
final class RoleController extends AbstractController
{
    public function __construct(
        private readonly RoleService $roleService,
    ) {
    }

    #[Route('', methods: ['GET'])]
    #[OA\Get(summary: 'Listar roles con paginación')]
    #[OA\Response(response: 200, description: 'Lista paginada de roles')]
    public function index(
        #[MapQueryString] ?PaginationRequest $pagination,
    ): JsonResponse {
        $pagination ??= new PaginationRequest();
        return $this->json($this->roleService->list($pagination));
    }

    #[Route('/{id}', methods: ['GET'], requirements: ['id' => '\d+'])]
    #[OA\Get(summary: 'Obtener un rol con sus permisos')]
    #[OA\Response(response: 200, description: 'Rol con permisos')]
    #[OA\Response(response: 404, description: 'Rol no encontrado')]
    public function show(int $id): JsonResponse
    {
        return $this->json($this->roleService->show($id));
    }

    #[Route('', methods: ['POST'])]
    #[OA\Post(
        summary: 'Crear un nuevo rol',
        requestBody: new OA\RequestBody(
            content: new OA\JsonContent(ref: new Model(type: CreateRoleRequest::class)),
        ),
    )]
    #[OA\Response(response: 201, description: 'Rol creado exitosamente')]
    public function create(
        #[MapRequestPayload] CreateRoleRequest $request,
    ): JsonResponse {
        return $this->json($this->roleService->create($request), Response::HTTP_CREATED);
    }

    #[Route('/{id}', methods: ['PUT'], requirements: ['id' => '\d+'])]
    #[OA\Put(
        summary: 'Actualizar un rol',
        requestBody: new OA\RequestBody(
            content: new OA\JsonContent(ref: new Model(type: UpdateRoleRequest::class)),
        ),
    )]
    #[OA\Response(response: 200, description: 'Rol actualizado')]
    #[OA\Response(response: 404, description: 'Rol no encontrado')]
    public function update(
        int $id,
        #[MapRequestPayload] UpdateRoleRequest $request,
    ): JsonResponse {
        return $this->json($this->roleService->update($id, $request));
    }

    #[Route('/{id}', methods: ['DELETE'], requirements: ['id' => '\d+'])]
    #[OA\Delete(summary: 'Eliminar un rol')]
    #[OA\Response(response: 204, description: 'Rol eliminado')]
    #[OA\Response(response: 404, description: 'Rol no encontrado')]
    public function delete(int $id): JsonResponse
    {
        $this->roleService->delete($id);
        return $this->json(null, Response::HTTP_NO_CONTENT);
    }

    #[Route('/{id}/permissions', methods: ['PUT'], requirements: ['id' => '\d+'])]
    #[OA\Put(summary: 'Actualizar permisos de un rol')]
    #[OA\Response(response: 200, description: 'Permisos actualizados')]
    #[OA\Response(response: 404, description: 'Rol no encontrado')]
    public function updatePermissions(
        int $id,
        #[MapRequestPayload] UpdateRolePermissionsRequest $request,
    ): JsonResponse {
        return $this->json($this->roleService->updatePermissions($id, $request));
    }

    #[Route('/meta', methods: ['GET'])]
    #[OA\Get(summary: 'Obtener módulos y acciones disponibles para configurar permisos')]
    #[OA\Response(response: 200, description: 'Módulos y acciones')]
    public function meta(): JsonResponse
    {
        return $this->json([
            'modules' => $this->roleService->getAllModules(),
            'actions' => $this->roleService->getAllActions(),
        ]);
    }
}
