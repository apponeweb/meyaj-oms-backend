<?php

declare(strict_types=1);

namespace App\DTO\Response;

use App\Entity\WarehouseType;

final readonly class WarehouseTypeResponse
{
    public int $id;
    public string $name;
    public ?string $description;
    public bool $isActive;
    public string $createdAt;
    public string $updatedAt;

    public function __construct(WarehouseType $warehouseType)
    {
        $this->id = $warehouseType->getId();
        $this->name = $warehouseType->getName();
        $this->description = $warehouseType->getDescription();
        $this->isActive = $warehouseType->isActive();
        $this->createdAt = $warehouseType->getCreatedAt()->format(\DateTimeInterface::ATOM);
        $this->updatedAt = $warehouseType->getUpdatedAt()->format(\DateTimeInterface::ATOM);
    }
}
