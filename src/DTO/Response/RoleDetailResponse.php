<?php

declare(strict_types=1);

namespace App\DTO\Response;

use App\Entity\Role;
use App\Entity\RoleActionPermission;
use App\Entity\RoleModulePermission;
use App\Repository\AppFunctionRepository;

final readonly class RoleDetailResponse
{
    public int $id;
    public string $name;
    public ?string $description;
    public bool $active;
    public array $permissions;
    public string $createdAt;

    public function __construct(Role $role, AppFunctionRepository $appFunctionRepository)
    {
        $this->id = $role->getId();
        $this->name = $role->getName();
        $this->description = $role->getDescription();
        $this->active = $role->isActive();
        $this->createdAt = $role->getCreatedAt()->format(\DateTimeInterface::ATOM);

        // Index action permissions by function_id -> action_code
        $actionMap = [];
        /** @var RoleActionPermission $ap */
        foreach ($role->getActionPermissions() as $ap) {
            $fnId = $ap->getAppFunction()->getId();
            $actionMap[$fnId][$ap->getAction()->getCode()] = $ap->isAllowed();
        }

        $perms = [];
        /** @var RoleModulePermission $mp */
        foreach ($role->getModulePermissions() as $mp) {
            $module = $mp->getAppModule();
            $functions = $appFunctionRepository->findByModuleId($module->getId());

            $fnPerms = [];
            foreach ($functions as $fn) {
                $fnActions = $actionMap[$fn->getId()] ?? [];
                $fnPerms[] = [
                    'functionCode' => $fn->getCode(),
                    'functionName' => $fn->getName(),
                    'actions' => $fnActions,
                ];
            }

            $perms[] = [
                'moduleCode' => $module->getCode(),
                'moduleName' => $module->getName(),
                'canAccess' => $mp->canAccess(),
                'functions' => $fnPerms,
            ];
        }

        $this->permissions = $perms;
    }
}
