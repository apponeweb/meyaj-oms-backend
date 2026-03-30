<?php

declare(strict_types=1);

namespace App\EventListener;

use App\Entity\User;
use App\Repository\UserSessionRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\KernelEvents;

#[AsEventListener(event: KernelEvents::REQUEST, priority: -10)]
final class SessionActivityListener
{
    public function __construct(
        private readonly Security $security,
        private readonly UserSessionRepository $sessionRepository,
        private readonly EntityManagerInterface $em,
        private readonly int $sessionTimeoutMinutes,
    ) {
    }

    public function __invoke(RequestEvent $event): void
    {
        if (!$event->isMainRequest()) {
            return;
        }

        $user = $this->security->getUser();
        if (!$user instanceof User) {
            return;
        }

        $request = $event->getRequest();
        $authHeader = $request->headers->get('Authorization', '');
        if (!str_starts_with($authHeader, 'Bearer ')) {
            return;
        }

        $token = substr($authHeader, 7);
        $session = $this->sessionRepository->findActiveByToken($token);

        if ($session === null) {
            return;
        }

        // Check if user is active
        if (!$user->isActive()) {
            $event->setResponse(new JsonResponse(
                ['error' => ['code' => 403, 'message' => 'account_disabled']],
                Response::HTTP_FORBIDDEN,
            ));
            return;
        }

        // Check if session has expired due to inactivity
        $threshold = new \DateTimeImmutable("-{$this->sessionTimeoutMinutes} minutes");
        if ($session->getLastActivityAt() < $threshold) {
            $session->setActive(false);
            $session->setLogoutAt(new \DateTimeImmutable());
            $this->em->flush();

            $event->setResponse(new JsonResponse(
                ['error' => ['code' => 401, 'message' => 'session_expired']],
                Response::HTTP_UNAUTHORIZED,
            ));
            return;
        }

        // Update last activity
        $session->setLastActivityAt(new \DateTimeImmutable());
        $this->em->flush();
    }
}
