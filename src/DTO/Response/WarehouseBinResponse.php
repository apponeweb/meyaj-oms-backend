<?php

declare(strict_types=1);

namespace App\DTO\Response;

use App\Entity\WarehouseBin;

final readonly class WarehouseBinResponse
{
    public int $id;
    public int $warehouseId;
    public string $warehouseName;
    public string $warehouseCode;
    public string $code;
    public string $name;
    public ?string $zone;
    public ?string $binType;
    public ?int $capacity;
    public bool $isActive;
    public string $createdAt;
    public string $updatedAt;

    public function __construct(WarehouseBin $bin)
    {
        $this->id = $bin->getId();
        $this->warehouseId = $bin->getWarehouse()->getId();
        $this->warehouseName = $bin->getWarehouse()->getName();
        $this->warehouseCode = $bin->getWarehouse()->getCode();
        $this->code = $bin->getCode();
        $this->name = $bin->getName();
        $this->zone = $bin->getZone();
        $this->binType = $bin->getBinType();
        $this->capacity = $bin->getCapacity();
        $this->isActive = $bin->isActive();
        $this->createdAt = $bin->getCreatedAt()->format(\DateTimeInterface::ATOM);
        $this->updatedAt = $bin->getUpdatedAt()->format(\DateTimeInterface::ATOM);
    }
}
