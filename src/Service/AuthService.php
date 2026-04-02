<?php

declare(strict_types=1);

namespace App\Service;

use App\DTO\Request\RegisterRequest;
use App\DTO\Response\UserResponse;
use App\Entity\User;
use App\Repository\RoleRepository;
use App\Repository\UserRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

final readonly class AuthService
{
    public function __construct(
        private EntityManagerInterface $em,
        private UserRepository $userRepository,
        private RoleRepository $roleRepository,
        private UserPasswordHasherInterface $passwordHasher,
    ) {
    }

    public function register(RegisterRequest $request): UserResponse
    {
        $existing = $this->userRepository->findOneBy(['email' => $request->email]);

        if ($existing !== null) {
            throw new ConflictHttpException('El email ya está registrado.');
        }

        $user = new User();
        $user->setName($request->name);
        $user->setEmail($request->email);
        $user->setPassword($this->passwordHasher->hashPassword($user, $request->password));

        $adminRole = $this->roleRepository->find(1);
        if ($adminRole !== null) {
            $user->setRole($adminRole);
        }

        $this->em->persist($user);
        $this->em->flush();

        return new UserResponse($user);
    }
}
