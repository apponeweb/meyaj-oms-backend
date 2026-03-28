<?php

declare(strict_types=1);

namespace App\Entity;

use App\Repository\RoleModulePermissionRepository;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: RoleModulePermissionRepository::class)]
#[ORM\Table(name: 'role_module_permission')]
#[ORM\UniqueConstraint(name: 'unique_role_module', columns: ['role_id', 'app_module_id'])]
class RoleModulePermission
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: Role::class, inversedBy: 'modulePermissions')]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private Role $role;

    #[ORM\ManyToOne(targetEntity: AppModule::class, inversedBy: 'rolePermissions')]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private AppModule $appModule;

    #[ORM\Column]
    private bool $canAccess = false;

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getRole(): Role
    {
        return $this->role;
    }

    public function setRole(Role $role): static
    {
        $this->role = $role;
        return $this;
    }

    public function getAppModule(): AppModule
    {
        return $this->appModule;
    }

    public function setAppModule(AppModule $appModule): static
    {
        $this->appModule = $appModule;
        return $this;
    }

    public function canAccess(): bool
    {
        return $this->canAccess;
    }

    public function setCanAccess(bool $canAccess): static
    {
        $this->canAccess = $canAccess;
        return $this;
    }
}
