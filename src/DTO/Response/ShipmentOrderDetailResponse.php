<?php

declare(strict_types=1);

namespace App\DTO\Response;

use App\Entity\SalesOrderItem;
use App\Entity\ShipmentOrder;
use App\Entity\ShipmentOrderItem;

final readonly class ShipmentOrderDetailResponse
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
    public array $items;
    public string $createdAt;
    public string $updatedAt;

    /**
     * @param array<int, array<array{id: int, serial: string, status: string}>> $unitsByItem
     */
    public function __construct(ShipmentOrder $sh, array $unitsByItem = [])
    {
        $this->id = $sh->getId();

        $so = $sh->getSalesOrder();
        $this->salesOrder = [
            'id' => $so->getId(),
            'folio' => $so->getFolio(),
            'customer' => [
                'id' => $so->getCustomer()->getId(),
                'name' => $so->getCustomer()->getName(),
            ],
            'total' => $so->getTotal(),
            'status' => $so->getStatus(),
            'items' => array_map(static fn (SalesOrderItem $item) => [
                'id' => $item->getId(),
                'paca' => [
                    'id' => $item->getPaca()->getId(),
                    'code' => $item->getPaca()->getCode(),
                    'name' => $item->getPaca()->getName(),
                ],
                'quantity' => $item->getQuantity(),
                'unitPrice' => $item->getUnitPrice(),
                'discount' => $item->getDiscount(),
                'totalPrice' => $item->getTotalPrice(),
                'notes' => $item->getNotes(),
                'units' => $unitsByItem[$item->getId()] ?? [],
            ], $so->getItems()->toArray()),
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

        $this->items = array_map(static fn (ShipmentOrderItem $item) => [
            'id' => $item->getId(),
            'pacaUnit' => [
                'id' => $item->getPacaUnit()->getId(),
                'serial' => $item->getPacaUnit()->getSerial(),
                'paca' => [
                    'id' => $item->getPacaUnit()->getPaca()->getId(),
                    'code' => $item->getPacaUnit()->getPaca()->getCode(),
                    'name' => $item->getPacaUnit()->getPaca()->getName(),
                ],
            ],
            'scannedAt' => $item->getScannedAt()->format(\DateTimeInterface::ATOM),
            'scannedBy' => [
                'id' => $item->getScannedBy()->getId(),
                'name' => $item->getScannedBy()->getName(),
            ],
        ], $sh->getItems()->toArray());

        $this->createdAt = $sh->getCreatedAt()->format(\DateTimeInterface::ATOM);
        $this->updatedAt = $sh->getUpdatedAt()->format(\DateTimeInterface::ATOM);
    }
}
