<?php

declare(strict_types=1);

namespace App\Service;

use App\Entity\User;
use App\Repository\AppFunctionRepository;
use App\Repository\AppModuleRepository;
use App\Repository\RoleActionPermissionRepository;
use App\Repository\RoleModulePermissionRepository;

class PermissionService
{
    public function __construct(
        private readonly AppModuleRepository $appModuleRepository,
        private readonly AppFunctionRepository $appFunctionRepository,
        private readonly RoleModulePermissionRepository $roleModulePermissionRepository,
        private readonly RoleActionPermissionRepository $roleActionPermissionRepository,
    ) {
    }

    /**
     * Returns accessible modules with their function-level permissions for a user.
     *
     * @return array<int, array{code: string, name: string, icon: string, functions: array}>
     */
    public function getAccessibleModules(User $user): array
    {
        $role = $user->getRole();

        if ($role === null) {
            return [];
        }

        $modulePermissions = $this->roleModulePermissionRepository->findByRoleId($role->getId());
        $result = [];

        foreach ($modulePermissions as $mp) {
            $module = $mp->getAppModule();
            $functions = $this->appFunctionRepository->findByModuleId($module->getId());
            $actionPerms = $this->roleActionPermissionRepository->findByRoleAndModuleFunctions(
                $role->getId(),
                $module->getId(),
            );

            // Index action perms by function_id -> action_code
            $actionMap = [];
            foreach ($actionPerms as $ap) {
                $actionMap[$ap->getAppFunction()->getId()][$ap->getAction()->getCode()] = true;
            }

            $fnList = [];
            foreach ($functions as $fn) {
                $fnPerms = $actionMap[$fn->getId()] ?? [];
                $fnList[] = [
                    'code' => $fn->getCode(),
                    'name' => $fn->getName(),
                    'permissions' => [
                        'create' => $fnPerms['create'] ?? false,
                        'read' => $fnPerms['read'] ?? false,
                        'update' => $fnPerms['update'] ?? false,
                        'delete' => $fnPerms['delete'] ?? false,
                        'export' => $fnPerms['export'] ?? false,
                    ],
                ];
            }

            $result[] = [
                'code' => $module->getCode(),
                'name' => $module->getName(),
                'icon' => $module->getIcon(),
                'functions' => $fnList,
            ];
        }

        return $result;
    }
}
