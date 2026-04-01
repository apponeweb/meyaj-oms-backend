<?php

declare(strict_types=1);

namespace App\DTO\Response;

use App\Entity\InventoryMovement;

final readonly class InventoryMovementResponse
{
    public int $id;
    public array $paca;
    public array $warehouse;
    public ?array $warehouseBin;
    public array $reason;
    public array $user;
    public string $movementType;
    public ?string $referenceType;
    public ?int $referenceId;
    public int $qtyIn;
    public int $qtyOut;
    public int $balanceAfter;
    public ?string $unitCost;
    public ?string $notes;
    public string $createdAt;

    public function __construct(InventoryMovement $m)
    {
        $this->id = $m->getId();
        $this->paca = [
            'id' => $m->getPaca()->getId(),
            'code' => $m->getPaca()->getCode(),
            'name' => $m->getPaca()->getName(),
        ];
        $this->warehouse = [
            'id' => $m->getWarehouse()->getId(),
            'code' => $m->getWarehouse()->getCode(),
            'name' => $m->getWarehouse()->getName(),
        ];
        $this->warehouseBin = $m->getWarehouseBin() ? [
            'id' => $m->getWarehouseBin()->getId(),
            'code' => $m->getWarehouseBin()->getCode(),
            'name' => $m->getWarehouseBin()->getName(),
        ] : null;
        $this->reason = [
            'id' => $m->getReason()->getId(),
            'code' => $m->getReason()->getCode(),
            'name' => $m->getReason()->getName(),
            'direction' => $m->getReason()->getDirection(),
        ];
        $this->user = [
            'id' => $m->getUser()->getId(),
            'name' => $m->getUser()->getName(),
        ];
        $this->movementType = $m->getMovementType();
        $this->referenceType = $m->getReferenceType();
        $this->referenceId = $m->getReferenceId();
        $this->qtyIn = $m->getQtyIn();
        $this->qtyOut = $m->getQtyOut();
        $this->balanceAfter = $m->getBalanceAfter();
        $this->unitCost = $m->getUnitCost();
        $this->notes = $m->getNotes();
        $this->createdAt = $m->getCreatedAt()->format(\DateTimeInterface::ATOM);
    }
}
