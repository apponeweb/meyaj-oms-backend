<?php

declare(strict_types=1);

namespace App\DTO\Response;

use App\Entity\Paca;

final readonly class PacaResponse
{
    public int $id;
    public string $code;
    public string $name;
    public ?string $description;
    public ?array $brand;
    public ?array $label;
    public ?array $qualityGrade;
    public ?array $season;
    public ?array $gender;
    public ?array $garmentType;
    public ?array $fabricType;
    public ?array $sizeProfile;
    public ?array $supplier;
    public string $purchasePrice;
    public string $sellingPrice;
    public int $stock;
    public ?int $reservedStock;
    public ?int $availableStock;
    public ?array $stockByWarehouse;
    public ?int $pieceCount;
    public ?string $weight;
    public bool $active;
    public string $createdAt;
    public string $updatedAt;

    /**
     * @param array<array{warehouseId: int, warehouseName: string, available: int, reserved: int, total: int}>|null $stockByWarehouse
     */
    public function __construct(Paca $p, ?int $availableStock = null, ?array $stockByWarehouse = null, ?int $stock = null, ?int $reservedStock = null)
    {
        $this->id = $p->getId();
        $this->code = $p->getCode();
        $this->name = $p->getName();
        $this->description = $p->getDescription();
        $this->brand = $p->getBrand() ? ['id' => $p->getBrand()->getId(), 'name' => $p->getBrand()->getName()] : null;
        $this->label = $p->getLabel() ? ['id' => $p->getLabel()->getId(), 'name' => $p->getLabel()->getName()] : null;
        $this->qualityGrade = $p->getQualityGrade() ? ['id' => $p->getQualityGrade()->getId(), 'name' => $p->getQualityGrade()->getName()] : null;
        $this->season = $p->getSeason() ? ['id' => $p->getSeason()->getId(), 'name' => $p->getSeason()->getName()] : null;
        $this->gender = $p->getGender() ? ['id' => $p->getGender()->getId(), 'name' => $p->getGender()->getName()] : null;
        $this->garmentType = $p->getGarmentType() ? ['id' => $p->getGarmentType()->getId(), 'name' => $p->getGarmentType()->getName()] : null;
        $this->fabricType = $p->getFabricType() ? ['id' => $p->getFabricType()->getId(), 'name' => $p->getFabricType()->getName()] : null;
        $this->sizeProfile = $p->getSizeProfile() ? ['id' => $p->getSizeProfile()->getId(), 'name' => $p->getSizeProfile()->getName()] : null;
        $this->supplier = $p->getSupplier() ? ['id' => $p->getSupplier()->getId(), 'name' => $p->getSupplier()->getName()] : null;
        $this->purchasePrice = $p->getPurchasePrice();
        $this->sellingPrice = $p->getSellingPrice();
        $this->stock = $stock ?? $p->getCachedStock();
        $this->reservedStock = $reservedStock;
        $this->availableStock = $availableStock;
        $this->stockByWarehouse = $stockByWarehouse;
        $this->pieceCount = $p->getPieceCount();
        $this->weight = $p->getWeight();
        $this->active = $p->isActive();
        $this->createdAt = $p->getCreatedAt()->format(\DateTimeInterface::ATOM);
        $this->updatedAt = $p->getUpdatedAt()->format(\DateTimeInterface::ATOM);
    }
}
