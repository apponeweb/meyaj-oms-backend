<?php

declare(strict_types=1);

namespace App\DTO\Response;

use App\Entity\Department;

final readonly class DepartmentResponse
{
    public int $id;
    public int $branchId;
    public string $branchName;
    public string $name;
    public ?string $description;
    public bool $active;
    public string $createdAt;
    public string $updatedAt;

    public function __construct(Department $department)
    {
        $this->id = $department->getId();
        $this->branchId = $department->getBranch()->getId();
        $this->branchName = $department->getBranch()->getName();
        $this->name = $department->getName();
        $this->description = $department->getDescription();
        $this->active = $department->isActive();
        $this->createdAt = $department->getCreatedAt()->format(\DateTimeInterface::ATOM);
        $this->updatedAt = $department->getUpdatedAt()->format(\DateTimeInterface::ATOM);
    }
}
