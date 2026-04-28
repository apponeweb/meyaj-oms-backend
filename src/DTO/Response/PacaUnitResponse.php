<?php

declare(strict_types=1);

namespace App\DTO\Response;

use App\Entity\PacaUnit;

final readonly class PacaUnitResponse
{
    public int $id;
    public string $serial;
    public array $paca;
    public array $warehouse;
    public ?array $warehouseBin;
    public string $status;
    public ?int $salesOrderId;
    public ?int $salesOrderItemId;
    public ?string $labeledAt;
    public string $createdAt;
    public string $updatedAt;

    public function __construct(PacaUnit $u)
    {
        $this->id = $u->getId();
        $this->serial = $u->getSerial();
        $this->paca = [
            'id' => $u->getPaca()->getId(),
            'code' => $u->getPaca()->getCode(),
            'name' => $u->getPaca()->getName(),
        ];
        $this->warehouse = [
            'id' => $u->getWarehouse()->getId(),
            'name' => $u->getWarehouse()->getName(),
        ];
        $this->warehouseBin = $u->getWarehouseBin() ? [
            'id' => $u->getWarehouseBin()->getId(),
            'name' => $u->getWarehouseBin()->getName(),
        ] : null;
        $this->status = $u->getStatus();
        $this->salesOrderId = $u->getSalesOrder()?->getId();
        $this->salesOrderItemId = $u->getSalesOrderItem()?->getId();
        $this->labeledAt = $u->getLabeledAt()?->format(\DateTimeInterface::ATOM);
        $this->createdAt = $u->getCreatedAt()->format(\DateTimeInterface::ATOM);
        $this->updatedAt = $u->getUpdatedAt()->format(\DateTimeInterface::ATOM);
    }
}
