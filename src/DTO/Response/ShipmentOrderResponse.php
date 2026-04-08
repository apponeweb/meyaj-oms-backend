<?php

declare(strict_types=1);

namespace App\DTO\Response;

use App\Entity\ShipmentOrder;

final readonly class ShipmentOrderResponse
{
    public int $id;
    public array $salesOrder;
    public array $warehouse;
    public string $folio;
    public ?string $trackingNumber;
    public string $status;
    public ?string $carrier;
    public ?string $notes;
    public int $itemCount;
    public ?string $shippedAt;
    public ?string $deliveredAt;
    public array $createdBy;
    public string $createdAt;
    public string $updatedAt;

    public function __construct(ShipmentOrder $sh)
    {
        $this->id = $sh->getId();
        $this->salesOrder = [
            'id' => $sh->getSalesOrder()->getId(),
            'folio' => $sh->getSalesOrder()->getFolio(),
            'customer' => [
                'id' => $sh->getSalesOrder()->getCustomer()->getId(),
                'name' => $sh->getSalesOrder()->getCustomer()->getName(),
            ],
        ];
        $this->warehouse = [
            'id' => $sh->getWarehouse()->getId(),
            'name' => $sh->getWarehouse()->getName(),
        ];
        $this->folio = $sh->getFolio();
        $this->trackingNumber = $sh->getTrackingNumber();
        $this->status = $sh->getStatus();
        $this->carrier = $sh->getCarrier();
        $this->notes = $sh->getNotes();
        $this->itemCount = $sh->getItems()->count();
        $this->shippedAt = $sh->getShippedAt()?->format(\DateTimeInterface::ATOM);
        $this->deliveredAt = $sh->getDeliveredAt()?->format(\DateTimeInterface::ATOM);
        $this->createdBy = [
            'id' => $sh->getCreatedBy()->getId(),
            'name' => $sh->getCreatedBy()->getName(),
        ];
        $this->createdAt = $sh->getCreatedAt()->format(\DateTimeInterface::ATOM);
        $this->updatedAt = $sh->getUpdatedAt()->format(\DateTimeInterface::ATOM);
    }
}
