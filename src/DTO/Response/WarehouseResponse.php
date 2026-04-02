<?php

declare(strict_types=1);

namespace App\DTO\Response;

use App\Entity\Warehouse;

final readonly class WarehouseResponse
{
    public int $id;
    public int $companyId;
    public string $companyName;
    public ?string $companyImage;
    public string $code;
    public string $name;
    public int $warehouseTypeId;
    public string $warehouseTypeName;
    public ?string $address;
    public ?string $monthlyCost;
    public bool $isExternal;
    public bool $isActive;
    public string $createdAt;
    public string $updatedAt;

    public function __construct(Warehouse $warehouse)
    {
        $this->id = $warehouse->getId();
        $this->companyId = $warehouse->getCompany()->getId();
        $this->companyName = $warehouse->getCompany()->getName();
        $this->companyImage = $warehouse->getCompany()->getImage();
        $this->code = $warehouse->getCode();
        $this->name = $warehouse->getName();
        $this->warehouseTypeId = $warehouse->getWarehouseType()->getId();
        $this->warehouseTypeName = $warehouse->getWarehouseType()->getName();
        $this->address = $warehouse->getAddress();
        $this->monthlyCost = $warehouse->getMonthlyCost();
        $this->isExternal = $warehouse->isExternal();
        $this->isActive = $warehouse->isActive();
        $this->createdAt = $warehouse->getCreatedAt()->format(\DateTimeInterface::ATOM);
        $this->updatedAt = $warehouse->getUpdatedAt()->format(\DateTimeInterface::ATOM);
    }
}
