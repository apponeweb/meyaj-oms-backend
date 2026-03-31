<?php

declare(strict_types=1);

namespace App\EventListener;

use App\Entity\User;
use App\Entity\UserSession;
use App\Repository\UserSessionRepository;
use Doctrine\ORM\EntityManagerInterface;
use Lexik\Bundle\JWTAuthenticationBundle\Event\AuthenticationSuccessEvent;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Symfony\Component\HttpFoundation\RequestStack;

#[AsEventListener(event: 'lexik_jwt_authentication.on_authentication_success')]
final class AuthenticationSuccessListener
{
    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly RequestStack $requestStack,
        private readonly UserSessionRepository $sessionRepository,
    ) {
    }

    public function __invoke(AuthenticationSuccessEvent $event): void
    {
        $user = $event->getUser();
        if (!$user instanceof User) {
            return;
        }

        $data = $event->getData();
        $token = $data['token'] ?? '';
        $request = $this->requestStack->getCurrentRequest();
        $ipAddress = $request?->getClientIp();
        $userAgent = $request?->headers->get('User-Agent');

        // Deactivate previous sessions from the same user, IP and browser
        $this->sessionRepository->deactivateByUserAndClient($user, $ipAddress, $userAgent);

        $session = new UserSession();
        $session->setUser($user);
        $session->setToken($token);
        $session->setIpAddress($ipAddress);
        $session->setUserAgent($userAgent);
        $this->em->persist($session);
        $this->em->flush();

        $data['user'] = [
            'id' => $user->getId(),
            'name' => $user->getName(),
            'email' => $user->getEmail(),
            'roles' => $user->getRoles(),
            'roleId' => $user->getRole()?->getId(),
            'roleName' => $user->getRole()?->getName(),
            'image' => $user->getImage(),
            'active' => $user->isActive(),
        ];
        $event->setData($data);
    }
}
