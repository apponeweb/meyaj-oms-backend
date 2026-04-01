<?php

declare(strict_types=1);

namespace App\DTO\Response;

use App\Entity\InventorySnapshot;

final readonly class InventorySnapshotResponse
{
    public int $id;
    public array $company;
    public array $warehouse;
    public string $snapshotDate;
    public int $totalPacas;
    public int $totalStock;
    public string $totalValuePurchase;
    public string $totalValueSelling;
    public int $movementsIn;
    public int $movementsOut;
    public int $lowStockCount;
    public int $outOfStockCount;
    public ?array $dataJson;
    public string $createdAt;

    public function __construct(InventorySnapshot $snapshot)
    {
        $this->id = $snapshot->getId();
        $this->company = ['id' => $snapshot->getCompany()->getId(), 'name' => $snapshot->getCompany()->getName()];
        $this->warehouse = ['id' => $snapshot->getWarehouse()->getId(), 'code' => $snapshot->getWarehouse()->getCode(), 'name' => $snapshot->getWarehouse()->getName()];
        $this->snapshotDate = $snapshot->getSnapshotDate()->format('Y-m-d');
        $this->totalPacas = $snapshot->getTotalPacas();
        $this->totalStock = $snapshot->getTotalStock();
        $this->totalValuePurchase = $snapshot->getTotalValuePurchase();
        $this->totalValueSelling = $snapshot->getTotalValueSelling();
        $this->movementsIn = $snapshot->getMovementsIn();
        $this->movementsOut = $snapshot->getMovementsOut();
        $this->lowStockCount = $snapshot->getLowStockCount();
        $this->outOfStockCount = $snapshot->getOutOfStockCount();
        $this->dataJson = $snapshot->getDataJson();
        $this->createdAt = $snapshot->getCreatedAt()->format(\DateTimeInterface::ATOM);
    }
}
