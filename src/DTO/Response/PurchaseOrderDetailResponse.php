<?php

declare(strict_types=1);

namespace App\DTO\Response;

use App\Entity\PurchaseOrder;
use App\Entity\PurchaseOrderItem;

final readonly class PurchaseOrderDetailResponse
{
    public int $id;
    public array $company;
    public array $supplier;
    public array $user;
    public string $folio;
    public string $status;
    public string $orderDate;
    public ?string $expectedDate;
    public ?string $receivedDate;
    public string $subtotal;
    public string $tax;
    public string $discount;
    public string $total;
    public ?string $notes;
    public int $itemCount;
    public array $items;
    public string $createdAt;
    public string $updatedAt;

    public function __construct(PurchaseOrder $po)
    {
        $this->id = $po->getId();
        $this->company = [
            'id' => $po->getCompany()->getId(),
            'name' => $po->getCompany()->getName(),
        ];
        $this->supplier = [
            'id' => $po->getSupplier()->getId(),
            'name' => $po->getSupplier()->getName(),
        ];
        $this->user = [
            'id' => $po->getUser()->getId(),
            'name' => $po->getUser()->getName(),
        ];
        $this->folio = $po->getFolio();
        $this->status = $po->getStatus();
        $this->orderDate = $po->getOrderDate()->format('Y-m-d');
        $this->expectedDate = $po->getExpectedDate()?->format('Y-m-d');
        $this->receivedDate = $po->getReceivedDate()?->format('Y-m-d');
        $this->subtotal = $po->getSubtotal();
        $this->tax = $po->getTax();
        $this->discount = $po->getDiscount();
        $this->total = $po->getTotal();
        $this->notes = $po->getNotes();
        $this->itemCount = $po->getItems()->count();
        $this->items = array_map(static fn (PurchaseOrderItem $item) => [
            'id' => $item->getId(),
            'description' => $item->getDescription(),
            'expectedQty' => $item->getExpectedQty(),
            'receivedQty' => $item->getReceivedQty(),
            'unitPrice' => $item->getUnitPrice(),
            'totalPrice' => $item->getTotalPrice(),
            'status' => $item->getStatus(),
            'label' => $item->getLabel() ? [
                'id' => $item->getLabel()->getId(),
                'name' => $item->getLabel()->getName(),
            ] : null,
            'paca' => $item->getPaca() ? [
                'id' => $item->getPaca()->getId(),
                'code' => $item->getPaca()->getCode(),
                'name' => $item->getPaca()->getName(),
            ] : null,
            'notes' => $item->getNotes(),
        ], $po->getItems()->toArray());
        $this->createdAt = $po->getCreatedAt()->format(\DateTimeInterface::ATOM);
        $this->updatedAt = $po->getUpdatedAt()->format(\DateTimeInterface::ATOM);
    }
}
