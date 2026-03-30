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
use App\Repository\BranchRepository;
use App\Repository\CompanyRepository;
use App\Repository\DepartmentRepository;
use App\Repository\RoleRepository;
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
        private RoleRepository $roleRepository,
        private CompanyRepository $companyRepository,
        private BranchRepository $branchRepository,
        private DepartmentRepository $departmentRepository,
        private UserPasswordHasherInterface $passwordHasher,
        private Paginator $paginator,
    ) {
    }

    public function list(PaginationRequest $pagination): PaginatedResponse
    {
        $qb = $this->userRepository->createPaginatedQueryBuilder(
            search: $pagination->search,
            roleId: $pagination->roleId,
            active: $pagination->active,
            companyId: $pagination->companyId,
            branchId: $pagination->branchId,
            departmentId: $pagination->departmentId,
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
        $existingEmail = $this->userRepository->findOneBy(['email' => $request->email]);
        if ($existingEmail !== null) {
            throw new ConflictHttpException('Ya existe un usuario con el correo electrónico "' . $request->email . '". Por favor, utiliza otro correo.');
        }

        if ($request->phone !== null && $request->phone !== '') {
            $existingPhone = $this->userRepository->findOneBy(['phone' => $request->phone]);
            if ($existingPhone !== null) {
                throw new ConflictHttpException('Ya existe un usuario con el número de teléfono "' . $request->phone . '". Por favor, utiliza otro número.');
            }
        }

        $user = new User();
        $user->setName($request->name);
        $user->setLastName($request->lastName);
        $user->setPhone($request->phone);
        $user->setEmail($request->email);
        $user->setPassword($this->passwordHasher->hashPassword($user, $request->password));
        $user->setAcronym($request->acronym);
        if ($request->image !== null) $user->setImage($request->image);
        $user->setIsMobileAllowed($request->isMobileAllowed);
        $user->setRoles($request->roles);

        if ($request->roleId !== null) {
            $role = $this->roleRepository->find($request->roleId);
            $user->setRole($role);
        }

        if ($request->companyId !== null) {
            $company = $this->companyRepository->find($request->companyId);
            $user->setCompany($company);
        }

        if ($request->branchId !== null) {
            $branch = $this->branchRepository->find($request->branchId);
            $user->setBranch($branch);
        }

        if ($request->departmentId !== null) {
            $department = $this->departmentRepository->find($request->departmentId);
            $user->setDepartment($department);
        }

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

        if ($request->lastName !== null) {
            $user->setLastName($request->lastName);
        }

        if ($request->phone !== null) {
            $user->setPhone($request->phone);
        }

        if ($request->email !== null) {
            $existing = $this->userRepository->findOneBy(['email' => $request->email]);
            if ($existing !== null && $existing->getId() !== $user->getId()) {
                throw new ConflictHttpException('Ya existe un usuario con el correo electrónico "' . $request->email . '". Por favor, utiliza otro correo.');
            }
            $user->setEmail($request->email);
        }

        if ($request->phone !== null && $request->phone !== '') {
            $existingPhone = $this->userRepository->findOneBy(['phone' => $request->phone]);
            if ($existingPhone !== null && $existingPhone->getId() !== $user->getId()) {
                throw new ConflictHttpException('Ya existe un usuario con el número de teléfono "' . $request->phone . '". Por favor, utiliza otro número.');
            }
            $user->setPhone($request->phone);
        }

        if ($request->password !== null) {
            $user->setPassword($this->passwordHasher->hashPassword($user, $request->password));
        }

        if ($request->roles !== null) {
            $user->setRoles($request->roles);
        }

        if ($request->roleId !== null) {
            $role = $this->roleRepository->find($request->roleId);
            $user->setRole($role);
        }

        if ($request->companyId !== null) {
            $company = $this->companyRepository->find($request->companyId);
            $user->setCompany($company);
            // Reset branch and department when company changes
            $user->setBranch(null);
            $user->setDepartment(null);
        }

        if ($request->branchId !== null) {
            $branch = $this->branchRepository->find($request->branchId);
            $user->setBranch($branch);
        }

        if ($request->departmentId !== null) {
            $department = $this->departmentRepository->find($request->departmentId);
            $user->setDepartment($department);
        }

        if ($request->acronym !== null) {
            $user->setAcronym($request->acronym);
        }

        if ($request->image !== null) {
            $user->setImage($request->image);
        }

        if ($request->active !== null) {
            $user->setActive($request->active);
        }

        if ($request->isMobileAllowed !== null) {
            $user->setIsMobileAllowed($request->isMobileAllowed);
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
