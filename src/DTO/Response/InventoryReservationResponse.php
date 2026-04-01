<?php

declare(strict_types=1);

namespace App\DTO\Response;

use App\Entity\InventoryReservation;

final readonly class InventoryReservationResponse
{
    public int $id;
    public ?int $salesOrderId;
    public ?int $salesOrderItemId;
    public array $paca;
    public array $user;
    public int $quantity;
    public string $status;
    public ?string $expiresAt;
    public string $createdAt;
    public string $updatedAt;

    public function __construct(InventoryReservation $r)
    {
        $this->id = $r->getId();
        $this->salesOrderId = $r->getSalesOrderId();
        $this->salesOrderItemId = $r->getSalesOrderItemId();
        $this->paca = [
            'id' => $r->getPaca()->getId(),
            'code' => $r->getPaca()->getCode(),
            'name' => $r->getPaca()->getName(),
        ];
        $this->user = [
            'id' => $r->getUser()->getId(),
            'name' => $r->getUser()->getName(),
        ];
        $this->quantity = $r->getQuantity();
        $this->status = $r->getStatus();
        $this->expiresAt = $r->getExpiresAt()?->format(\DateTimeInterface::ATOM);
        $this->createdAt = $r->getCreatedAt()->format(\DateTimeInterface::ATOM);
        $this->updatedAt = $r->getUpdatedAt()->format(\DateTimeInterface::ATOM);
    }
}
