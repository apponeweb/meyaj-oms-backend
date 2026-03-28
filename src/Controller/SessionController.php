<?php

declare(strict_types=1);

namespace App\Controller;

use App\Repository\UserSessionRepository;
use OpenApi\Attributes as OA;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/api/seguridad/sessions')]
#[OA\Tag(name: 'Seguridad - Sesiones')]
final class SessionController extends AbstractController
{
    public function __construct(
        private readonly UserSessionRepository $sessionRepository,
    ) {
    }

    #[Route('', methods: ['GET'])]
    #[OA\Get(summary: 'Listar sesiones de usuario')]
    #[OA\Response(response: 200, description: 'Lista de sesiones')]
    public function index(): JsonResponse
    {
        $sessions = $this->sessionRepository->findBy([], ['loginAt' => 'DESC'], 50);

        $result = array_map(static fn ($s) => [
            'id' => $s->getId(),
            'userId' => $s->getUser()->getId(),
            'userName' => $s->getUser()->getName(),
            'userEmail' => $s->getUser()->getEmail(),
            'ipAddress' => $s->getIpAddress(),
            'loginAt' => $s->getLoginAt()->format(\DateTimeInterface::ATOM),
            'logoutAt' => $s->getLogoutAt()?->format(\DateTimeInterface::ATOM),
            'active' => $s->isActive(),
        ], $sessions);

        return $this->json(['data' => $result]);
    }
}
