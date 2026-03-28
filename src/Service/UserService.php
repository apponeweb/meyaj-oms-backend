<?php

declare(strict_types=1);

namespace App\Service;

use App\DTO\Request\CreateUserRequest;
use App\DTO\Request\UpdateUserRequest;
use App\DTO\Response\UserResponse;
use App\Entity\User;
use App\Pagination\PaginatedResponse;
use App\Pagination\PaginationRequest;
use App\Pagination\Paginator;
use App\Repository\UserRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

final readonly class UserService
{
    public function __construct(
        private EntityManagerInterface $em,
        private UserRepository $userRepository,
        private UserPasswordHasherInterface $passwordHasher,
        private Paginator $paginator,
    ) {
    }

    public function list(PaginationRequest $pagination): PaginatedResponse
    {
        $qb = $this->userRepository->createPaginatedQueryBuilder(
            search: $pagination->search,
        );

        $result = $this->paginator->paginate($qb, $pagination);

        return new PaginatedResponse(
            data: array_map(
                static fn (User $user) => new UserResponse($user),
                $result->data,
            ),
            meta: $result->meta,
        );
    }

    public function show(int $id): UserResponse
    {
        $user = $this->userRepository->find($id);

        if ($user === null) {
            throw new NotFoundHttpException(sprintf('Usuario con ID %d no encontrado.', $id));
        }

        return new UserResponse($user);
    }

    public function create(CreateUserRequest $request): UserResponse
    {
        $existing = $this->userRepository->findOneBy(['email' => $request->email]);

        if ($existing !== null) {
            throw new ConflictHttpException('El email ya está registrado.');
        }

        $user = new User();
        $user->setName($request->name);
        $user->setEmail($request->email);
        $user->setPassword($this->passwordHasher->hashPassword($user, $request->password));
        $user->setRoles($request->roles);

        $this->em->persist($user);
        $this->em->flush();

        return new UserResponse($user);
    }

    public function update(int $id, UpdateUserRequest $request): UserResponse
    {
        $user = $this->userRepository->find($id);

        if ($user === null) {
            throw new NotFoundHttpException(sprintf('Usuario con ID %d no encontrado.', $id));
        }

        if ($request->name !== null) {
            $user->setName($request->name);
        }

        if ($request->email !== null) {
            $existing = $this->userRepository->findOneBy(['email' => $request->email]);
            if ($existing !== null && $existing->getId() !== $user->getId()) {
                throw new ConflictHttpException('El email ya está registrado.');
            }
            $user->setEmail($request->email);
        }

        if ($request->password !== null) {
            $user->setPassword($this->passwordHasher->hashPassword($user, $request->password));
        }

        if ($request->roles !== null) {
            $user->setRoles($request->roles);
        }

        $this->em->flush();

        return new UserResponse($user);
    }

    public function delete(int $id): void
    {
        $user = $this->userRepository->find($id);

        if ($user === null) {
            throw new NotFoundHttpException(sprintf('Usuario con ID %d no encontrado.', $id));
        }

        $this->em->remove($user);
        $this->em->flush();
    }
}
