<?php

declare(strict_types=1);

namespace App\Controller;

use App\Entity\User;
use App\Service\PermissionService;
use OpenApi\Attributes as OA;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/api')]
#[OA\Tag(name: 'Modules')]
final class ModuleController extends AbstractController
{
    public function __construct(
        private readonly PermissionService $permissionService,
    ) {
    }

    #[Route('/modules', methods: ['GET'])]
    #[OA\Get(summary: 'Obtener módulos accesibles para el usuario autenticado')]
    #[OA\Response(response: 200, description: 'Lista de módulos con permisos')]
    public function list(): JsonResponse
    {
        /** @var User $user */
        $user = $this->getUser();

        $modules = $this->permissionService->getAccessibleModules($user);

        return $this->json(['modules' => $modules]);
    }
}
