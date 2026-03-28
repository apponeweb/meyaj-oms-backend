<?php

declare(strict_types=1);

namespace App\Controller;

use App\DTO\Request\RegisterRequest;
use App\Service\AuthService;
use Nelmio\ApiDocBundle\Attribute\Model;
use OpenApi\Attributes as OA;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\MapRequestPayload;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/api')]
#[OA\Tag(name: 'Auth')]
final class AuthController extends AbstractController
{
    public function __construct(
        private readonly AuthService $authService,
    ) {
    }

    #[Route('/register', methods: ['POST'])]
    #[OA\Post(
        summary: 'Registrar un nuevo usuario',
        requestBody: new OA\RequestBody(
            content: new OA\JsonContent(ref: new Model(type: RegisterRequest::class)),
        ),
    )]
    #[OA\Response(response: 201, description: 'Usuario registrado exitosamente')]
    #[OA\Response(response: 409, description: 'El email ya está registrado')]
    #[OA\Response(response: 422, description: 'Datos de validación inválidos')]
    public function register(
        #[MapRequestPayload] RegisterRequest $request,
    ): JsonResponse {
        $user = $this->authService->register($request);

        return $this->json($user, Response::HTTP_CREATED);
    }

    #[Route('/login', methods: ['POST'])]
    #[OA\Post(
        summary: 'Iniciar sesión y obtener JWT token',
        requestBody: new OA\RequestBody(
            content: new OA\JsonContent(
                properties: [
                    new OA\Property(property: 'username', type: 'string', example: 'user@example.com'),
                    new OA\Property(property: 'password', type: 'string', example: 'password123'),
                ],
            ),
        ),
    )]
    #[OA\Response(
        response: 200,
        description: 'Login exitoso',
        content: new OA\JsonContent(
            properties: [
                new OA\Property(property: 'token', type: 'string'),
            ],
        ),
    )]
    #[OA\Response(response: 401, description: 'Credenciales inválidas')]
    public function login(): JsonResponse
    {
        // This endpoint is handled by lexik/jwt-authentication-bundle via json_login
        // The method body is never reached
        throw new \LogicException('This should never be reached.');
    }
}
