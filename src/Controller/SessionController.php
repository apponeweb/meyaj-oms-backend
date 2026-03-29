<?php

declare(strict_types=1);

namespace App\Controller;

use App\Entity\User;
use App\Pagination\PaginationRequest;
use App\Pagination\Paginator;
use App\Repository\UserSessionRepository;
use Doctrine\ORM\EntityManagerInterface;
use OpenApi\Attributes as OA;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\MapQueryString;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/api')]
#[OA\Tag(name: 'Seguridad - Sesiones')]
final class SessionController extends AbstractController
{
    public function __construct(
        private readonly UserSessionRepository $sessionRepository,
        private readonly EntityManagerInterface $em,
        private readonly Paginator $paginator,
        private readonly int $sessionTimeoutMinutes,
    ) {
    }

    #[Route('/seguridad/sessions', methods: ['GET'])]
    #[OA\Get(summary: 'Listar sesiones activas')]
    #[OA\Response(response: 200, description: 'Lista de sesiones activas')]
    public function index(
        Request $request,
        #[MapQueryString] ?PaginationRequest $pagination = null,
    ): JsonResponse {
        $pagination ??= new PaginationRequest();

        // First, expire any stale sessions
        $this->sessionRepository->expireInactiveSessions($this->sessionTimeoutMinutes);

        // Clear identity map so the next query reflects the DB updates
        $this->em->clear();

        $active = $request->query->get('active');

        $qb = $this->sessionRepository->createQueryBuilder('s')
            ->join('s.user', 'u')
            ->addSelect('u');

        if ($active !== null) {
            $qb->andWhere('s.active = :active')->setParameter('active', $active === '1');
        }

        if ($pagination->search) {
            $qb->andWhere('u.name LIKE :s OR u.email LIKE :s')->setParameter('s', "%{$pagination->search}%");
        }

        if ($pagination->sort === null) {
            $qb->orderBy('s.loginAt', 'DESC');
        }

        $page = $this->paginator->paginate($qb, $pagination);

        $data = array_map(fn ($s) => [
            'id' => $s->getId(),
            'userId' => $s->getUser()->getId(),
            'userName' => $s->getUser()->getName(),
            'userEmail' => $s->getUser()->getEmail(),
            'ipAddress' => $s->getIpAddress(),
            'userAgent' => $s->getUserAgent(),
            'browser' => self::parseBrowser($s->getUserAgent()),
            'loginAt' => $s->getLoginAt()->format(\DateTimeInterface::ATOM),
            'logoutAt' => $s->getLogoutAt()?->format(\DateTimeInterface::ATOM),
            'lastActivityAt' => $s->getLastActivityAt()->format(\DateTimeInterface::ATOM),
            'active' => $s->isActive(),
        ], $page->data);

        return $this->json(['data' => $data, 'meta' => $page->meta]);
    }

    #[Route('/logout', methods: ['POST'])]
    #[OA\Post(summary: 'Cerrar sesión del usuario autenticado')]
    #[OA\Response(response: 200, description: 'Sesión cerrada')]
    public function logout(Request $request): JsonResponse
    {
        $authHeader = $request->headers->get('Authorization', '');
        if (str_starts_with($authHeader, 'Bearer ')) {
            $token = substr($authHeader, 7);
            $session = $this->sessionRepository->findActiveByToken($token);

            if ($session !== null) {
                $session->setActive(false);
                $session->setLogoutAt(new \DateTimeImmutable());
                $this->em->flush();
            }
        }

        return $this->json(['message' => 'Sesión cerrada']);
    }

    #[Route('/session/config', methods: ['GET'])]
    #[OA\Get(summary: 'Obtener configuración de sesión')]
    #[OA\Response(response: 200, description: 'Configuración de timeout')]
    public function config(): JsonResponse
    {
        return $this->json([
            'timeoutMinutes' => $this->sessionTimeoutMinutes,
        ]);
    }

    private static function parseBrowser(?string $ua): string
    {
        if ($ua === null || $ua === '') {
            return 'Desconocido';
        }

        // Order matters: check specific browsers before generic engines
        $browsers = [
            'OPR|Opera'   => 'Opera',
            'Edg'         => 'Edge',
            'Chrome'      => 'Chrome',
            'Safari'      => 'Safari',
            'Firefox'     => 'Firefox',
        ];

        foreach ($browsers as $pattern => $name) {
            if (preg_match('/(' . $pattern . ')[\/ ]([\d.]+)/i', $ua, $m)) {
                return $name . ' ' . $m[2];
            }
        }

        return 'Desconocido';
    }
}
