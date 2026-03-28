<?php

declare(strict_types=1);

namespace App\DTO\Response;

use App\Entity\Role;
use App\Entity\RoleActionPermission;
use App\Entity\RoleModulePermission;

final readonly class RoleDetailResponse
{
    public int $id;
    public string $name;
    public ?string $description;
    public bool $active;
    /** @var array<int, array{moduleCode: string, moduleName: string, canAccess: bool, actions: array<string, bool>}> */
    public array $permissions;
    public string $createdAt;

    public function __construct(Role $role)
    {
        $this->id = $role->getId();
        $this->name = $role->getName();
        $this->description = $role->getDescription();
        $this->active = $role->isActive();
        $this->createdAt = $role->getCreatedAt()->format(\DateTimeInterface::ATOM);

        $perms = [];
        /** @var RoleModulePermission $mp */
        foreach ($role->getModulePermissions() as $mp) {
            $module = $mp->getAppModule();
            $actionPerms = [];

            /** @var RoleActionPermission $ap */
            foreach ($role->getActionPermissions() as $ap) {
                if ($ap->getAppModule()->getId() === $module->getId()) {
                    $actionPerms[$ap->getAction()->getCode()] = $ap->isAllowed();
                }
            }

            $perms[] = [
                'moduleCode' => $module->getCode(),
                'moduleName' => $module->getName(),
                'canAccess' => $mp->canAccess(),
                'actions' => $actionPerms,
            ];
        }

        $this->permissions = $perms;
    }
}
