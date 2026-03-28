<?php

declare(strict_types=1);

namespace App\Service;

use App\Entity\User;
use App\Repository\AppModuleRepository;
use App\Repository\RoleActionPermissionRepository;
use App\Repository\RoleModulePermissionRepository;

class PermissionService
{
    public function __construct(
        private readonly AppModuleRepository $appModuleRepository,
        private readonly RoleModulePermissionRepository $roleModulePermissionRepository,
        private readonly RoleActionPermissionRepository $roleActionPermissionRepository,
    ) {
    }

    /**
     * Returns accessible modules with their action permissions for a user.
     * If user has no role assigned, returns all active modules (backward compatibility).
     *
     * @return array<int, array{code: string, name: string, icon: string, permissions: array<string, bool>}>
     */
    public function getAccessibleModules(User $user): array
    {
        $role = $user->getRole();

        // If no role assigned yet, return all active modules with full permissions
        if ($role === null) {
            $allModules = $this->appModuleRepository->findAllActive();
            $result = [];
            foreach ($allModules as $module) {
                $result[] = [
                    'code' => $module->getCode(),
                    'name' => $module->getName(),
                    'icon' => $module->getIcon(),
                    'permissions' => [
                        'create' => true,
                        'read' => true,
                        'update' => true,
                        'delete' => true,
                        'export' => true,
                    ],
                ];
            }
            return $result;
        }

        $modulePermissions = $this->roleModulePermissionRepository->findByRoleId($role->getId());
        $result = [];

        foreach ($modulePermissions as $mp) {
            $module = $mp->getAppModule();
            $actionPerms = $this->roleActionPermissionRepository->findByRoleAndModule(
                $role->getId(),
                $module->getId(),
            );

            $permissions = [
                'create' => false,
                'read' => false,
                'update' => false,
                'delete' => false,
                'export' => false,
            ];

            foreach ($actionPerms as $ap) {
                $permissions[$ap->getAction()->getCode()] = true;
            }

            $result[] = [
                'code' => $module->getCode(),
                'name' => $module->getName(),
                'icon' => $module->getIcon(),
                'permissions' => $permissions,
            ];
        }

        return $result;
    }
}
