<?php

declare(strict_types=1);

namespace App\DTO\Response;

use App\Entity\Role;

final readonly class RoleResponse
{
    public int $id;
    public string $name;
    public ?string $description;
    public bool $active;
    public int $usersCount;
    public string $createdAt;
    public string $updatedAt;

    public function __construct(Role $role)
    {
        $this->id = $role->getId();
        $this->name = $role->getName();
        $this->description = $role->getDescription();
        $this->active = $role->isActive();
        $this->usersCount = $role->getUsers()->count();
        $this->createdAt = $role->getCreatedAt()->format(\DateTimeInterface::ATOM);
        $this->updatedAt = $role->getUpdatedAt()->format(\DateTimeInterface::ATOM);
    }
}
