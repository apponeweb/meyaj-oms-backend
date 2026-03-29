<?php

declare(strict_types=1);

namespace App\Entity;

use App\Repository\RoleActionPermissionRepository;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: RoleActionPermissionRepository::class)]
#[ORM\Table(name: 'app_role_action_permission', options: ['comment' => 'Permisos granulares que definen qué acciones (crear, leer, actualizar, eliminar, exportar) puede realizar un rol sobre cada funcionalidad'])]
#[ORM\UniqueConstraint(name: 'unique_role_function_action', columns: ['role_id', 'app_function_id', 'action_id'])]
class RoleActionPermission
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: Role::class, inversedBy: 'actionPermissions')]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private Role $role;

    #[ORM\ManyToOne(targetEntity: AppFunction::class)]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private AppFunction $appFunction;

    #[ORM\ManyToOne(targetEntity: ActionCatalog::class)]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private ActionCatalog $action;

    #[ORM\Column]
    private bool $allowed = false;

    public function getId(): ?int { return $this->id; }

    public function getRole(): Role { return $this->role; }
    public function setRole(Role $role): static { $this->role = $role; return $this; }

    public function getAppFunction(): AppFunction { return $this->appFunction; }
    public function setAppFunction(AppFunction $appFunction): static { $this->appFunction = $appFunction; return $this; }

    public function getAction(): ActionCatalog { return $this->action; }
    public function setAction(ActionCatalog $action): static { $this->action = $action; return $this; }

    public function isAllowed(): bool { return $this->allowed; }
    public function setAllowed(bool $allowed): static { $this->allowed = $allowed; return $this; }
}
