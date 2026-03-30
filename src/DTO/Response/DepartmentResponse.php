<?php

declare(strict_types=1);

namespace App\DTO\Response;

use App\Entity\Department;

final readonly class DepartmentResponse
{
    public int $id;
    public int $companyId;
    public ?string $companyImage;
    public string $companyName;
    public string $name;
    public ?string $acronym;
    public ?string $description;
    public bool $active;
    public string $createdAt;
    public string $updatedAt;

    public function __construct(Department $department)
    {
        $this->id = $department->getId();
        $this->companyId = $department->getCompany()->getId();
        $this->companyName = $department->getCompany()->getName();
        $this->companyImage = $department->getCompany()->getImage();
        $this->name = $department->getName();
        $this->acronym = $department->getAcronym();
        $this->description = $department->getDescription();
        $this->active = $department->isActive();
        $this->createdAt = $department->getCreatedAt()->format(\DateTimeInterface::ATOM);
        $this->updatedAt = $department->getUpdatedAt()->format(\DateTimeInterface::ATOM);
    }
}
