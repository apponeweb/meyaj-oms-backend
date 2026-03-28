<?php

declare(strict_types=1);

namespace App\Controller;

use App\DTO\Request\CreateUserRequest;
use App\DTO\Request\UpdateUserRequest;
use App\Pagination\PaginationRequest;
use App\Service\UserService;
use Nelmio\ApiDocBundle\Attribute\Model;
use OpenApi\Attributes as OA;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\MapQueryString;
use Symfony\Component\HttpKernel\Attribute\MapRequestPayload;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/api/users')]
#[OA\Tag(name: 'Users')]
final class UserController extends AbstractController
{
    public function __construct(
        private readonly UserService $userService,
    ) {
    }

    #[Route('', methods: ['GET'])]
    #[OA\Get(
        summary: 'Listar usuarios con paginación y búsqueda',
        parameters: [
            new OA\Parameter(name: 'page', in: 'query', schema: new OA\Schema(type: 'integer', default: 1)),
            new OA\Parameter(name: 'limit', in: 'query', schema: new OA\Schema(type: 'integer', default: 10)),
            new OA\Parameter(name: 'sort', in: 'query', schema: new OA\Schema(type: 'string', enum: ['name', 'email', 'createdAt'])),
            new OA\Parameter(name: 'order', in: 'query', schema: new OA\Schema(type: 'string', enum: ['asc', 'desc'], default: 'asc')),
            new OA\Parameter(name: 'search', in: 'query', schema: new OA\Schema(type: 'string')),
        ],
    )]
    #[OA\Response(response: 200, description: 'Lista paginada de usuarios')]
    public function index(
        #[MapQueryString] ?PaginationRequest $pagination,
    ): JsonResponse {
        $pagination ??= new PaginationRequest();

        return $this->json($this->userService->list($pagination));
    }

    #[Route('/{id}', methods: ['GET'], requirements: ['id' => '\d+'])]
    #[OA\Get(summary: 'Obtener un usuario por ID')]
    #[OA\Response(response: 200, description: 'Usuario encontrado')]
    #[OA\Response(response: 404, description: 'Usuario no encontrado')]
    public function show(int $id): JsonResponse
    {
        return $this->json($this->userService->show($id));
    }

    #[Route('', methods: ['POST'])]
    #[OA\Post(
        summary: 'Crear un nuevo usuario',
        requestBody: new OA\RequestBody(
            content: new OA\JsonContent(ref: new Model(type: CreateUserRequest::class)),
        ),
    )]
    #[OA\Response(response: 201, description: 'Usuario creado exitosamente')]
    #[OA\Response(response: 409, description: 'El email ya está registrado')]
    #[OA\Response(response: 422, description: 'Datos de validación inválidos')]
    public function create(
        #[MapRequestPayload] CreateUserRequest $request,
    ): JsonResponse {
        return $this->json(
            $this->userService->create($request),
            Response::HTTP_CREATED,
        );
    }

    #[Route('/{id}', methods: ['PUT'], requirements: ['id' => '\d+'])]
    #[OA\Put(
        summary: 'Actualizar un usuario',
        requestBody: new OA\RequestBody(
            content: new OA\JsonContent(ref: new Model(type: UpdateUserRequest::class)),
        ),
    )]
    #[OA\Response(response: 200, description: 'Usuario actualizado')]
    #[OA\Response(response: 404, description: 'Usuario no encontrado')]
    #[OA\Response(response: 409, description: 'El email ya está registrado')]
    public function update(
        int $id,
        #[MapRequestPayload] UpdateUserRequest $request,
    ): JsonResponse {
        return $this->json($this->userService->update($id, $request));
    }

    #[Route('/{id}', methods: ['DELETE'], requirements: ['id' => '\d+'])]
    #[OA\Delete(summary: 'Eliminar un usuario')]
    #[OA\Response(response: 204, description: 'Usuario eliminado')]
    #[OA\Response(response: 404, description: 'Usuario no encontrado')]
    public function delete(int $id): JsonResponse
    {
        $this->userService->delete($id);

        return $this->json(null, Response::HTTP_NO_CONTENT);
    }
}
