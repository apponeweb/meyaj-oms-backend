<?php

declare(strict_types=1);

namespace App\Service;

use App\DTO\Request\CreateRoleRequest;
use App\DTO\Request\UpdateRolePermissionsRequest;
use App\DTO\Request\UpdateRoleRequest;
use App\DTO\Response\RoleDetailResponse;
use App\DTO\Response\RoleResponse;
use App\Entity\Role;
use App\Entity\RoleActionPermission;
use App\Entity\RoleModulePermission;
use App\Pagination\PaginatedResponse;
use App\Pagination\PaginationRequest;
use App\Pagination\Paginator;
use App\Repository\ActionCatalogRepository;
use App\Repository\AppFunctionRepository;
use App\Repository\AppModuleRepository;
use App\Repository\RoleRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

final readonly class RoleService
{
    public function __construct(
        private EntityManagerInterface $em,
        private RoleRepository $roleRepository,
        private AppModuleRepository $appModuleRepository,
        private AppFunctionRepository $appFunctionRepository,
        private ActionCatalogRepository $actionCatalogRepository,
        private Paginator $paginator,
    ) {
    }

    public function list(PaginationRequest $pagination): PaginatedResponse
    {
        $qb = $this->roleRepository->createPaginatedQueryBuilder(
            search: $pagination->search,
        );

        $result = $this->paginator->paginate($qb, $pagination, fetchJoinCollection: false);

        return new PaginatedResponse(
            data: array_map(
                static fn (Role $role) => new RoleResponse($role),
                $result->data,
            ),
            meta: $result->meta,
        );
    }

    public function show(int $id): RoleDetailResponse
    {
        $role = $this->roleRepository->find($id);
        if ($role === null) {
            throw new NotFoundHttpException(sprintf('Rol con ID %d no encontrado.', $id));
        }
        return new RoleDetailResponse($role, $this->appFunctionRepository);
    }

    public function create(CreateRoleRequest $request): RoleResponse
    {
        $role = new Role();
        $role->setName($request->name);
        $role->setDescription($request->description);

        $this->em->persist($role);
        $this->em->flush();

        return new RoleResponse($role);
    }

    public function update(int $id, UpdateRoleRequest $request): RoleResponse
    {
        $role = $this->roleRepository->find($id);
        if ($role === null) {
            throw new NotFoundHttpException(sprintf('Rol con ID %d no encontrado.', $id));
        }

        if ($request->name !== null) $role->setName($request->name);
        if ($request->description !== null) $role->setDescription($request->description);
        if ($request->active !== null) $role->setActive($request->active);

        $this->em->flush();

        return new RoleResponse($role);
    }

    public function delete(int $id): void
    {
        $role = $this->roleRepository->find($id);
        if ($role === null) {
            throw new NotFoundHttpException(sprintf('Rol con ID %d no encontrado.', $id));
        }
        $this->em->remove($role);
        $this->em->flush();
    }

    public function updatePermissions(int $id, UpdateRolePermissionsRequest $request): RoleDetailResponse
    {
        $role = $this->roleRepository->find($id);
        if ($role === null) {
            throw new NotFoundHttpException(sprintf('Rol con ID %d no encontrado.', $id));
        }

        // Remove existing permissions
        foreach ($role->getModulePermissions() as $mp) {
            $this->em->remove($mp);
        }
        foreach ($role->getActionPermissions() as $ap) {
            $this->em->remove($ap);
        }
        $this->em->flush();

        // Create new permissions
        foreach ($request->permissions as $perm) {
            $module = $this->appModuleRepository->findOneBy(['code' => $perm['moduleCode']]);
            if ($module === null) continue;

            $mp = new RoleModulePermission();
            $mp->setRole($role);
            $mp->setAppModule($module);
            $mp->setCanAccess($perm['canAccess']);
            $this->em->persist($mp);

            // Function-level action permissions
            if (isset($perm['functions']) && is_array($perm['functions'])) {
                foreach ($perm['functions'] as $fnPerm) {
                    $fn = $this->appFunctionRepository->findOneBy(['code' => $fnPerm['functionCode']]);
                    if ($fn === null) continue;

                    foreach ($fnPerm['actions'] as $actionCode => $allowed) {
                        if (!$allowed) continue;
                        $action = $this->actionCatalogRepository->findOneBy(['code' => $actionCode]);
                        if ($action === null) continue;

                        $ap = new RoleActionPermission();
                        $ap->setRole($role);
                        $ap->setAppFunction($fn);
                        $ap->setAction($action);
                        $ap->setAllowed(true);
                        $this->em->persist($ap);
                    }
                }
            }
        }

        $this->em->flush();
        $this->em->refresh($role);

        return new RoleDetailResponse($role, $this->appFunctionRepository);
    }

    /** @return array<int, array{code: string, name: string, functions: array}> */
    public function getAllModules(): array
    {
        $modules = $this->appModuleRepository->findAllActive();
        return array_map(function ($m) {
            $functions = $this->appFunctionRepository->findByModuleId($m->getId());
            return [
                'code' => $m->getCode(),
                'name' => $m->getName(),
                'functions' => array_map(
                    static fn ($f) => ['code' => $f->getCode(), 'name' => $f->getName()],
                    $functions,
                ),
            ];
        }, $modules);
    }

    /** @return array<int, array{code: string, name: string}> */
    public function getAllActions(): array
    {
        $actions = $this->actionCatalogRepository->findAll();
        return array_map(static fn ($a) => ['code' => $a->getCode(), 'name' => $a->getName()], $actions);
    }
}
