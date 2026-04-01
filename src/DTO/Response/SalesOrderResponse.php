<?php

declare(strict_types=1);

namespace App\DTO\Response;

use App\Entity\SalesOrder;

final readonly class SalesOrderResponse
{
    public int $id;
    public array $company;
    public array $customer;
    public array $user;
    public ?array $seller;
    public string $folio;
    public string $channel;
    public string $orderType;
    public string $status;
    public string $paymentStatus;
    public string $deliveryStatus;
    public string $subtotal;
    public string $tax;
    public string $discount;
    public string $shippingCost;
    public string $total;
    public ?string $notes;
    public ?string $sourceWhatsapp;
    public ?string $estimatedDelivery;
    public ?string $deliveredAt;
    public int $itemCount;
    public int $statusHistoryCount;
    public string $createdAt;
    public string $updatedAt;

    public function __construct(SalesOrder $so)
    {
        $this->id = $so->getId();
        $this->company = [
            'id' => $so->getCompany()->getId(),
            'name' => $so->getCompany()->getName(),
        ];
        $this->customer = [
            'id' => $so->getCustomer()->getId(),
            'name' => $so->getCustomer()->getName(),
        ];
        $this->user = [
            'id' => $so->getUser()->getId(),
            'name' => $so->getUser()->getName(),
        ];
        $this->seller = $so->getSeller() ? [
            'id' => $so->getSeller()->getId(),
            'name' => $so->getSeller()->getName(),
        ] : null;
        $this->folio = $so->getFolio();
        $this->channel = $so->getChannel();
        $this->orderType = $so->getOrderType();
        $this->status = $so->getStatus();
        $this->paymentStatus = $so->getPaymentStatus();
        $this->deliveryStatus = $so->getDeliveryStatus();
        $this->subtotal = $so->getSubtotal();
        $this->tax = $so->getTax();
        $this->discount = $so->getDiscount();
        $this->shippingCost = $so->getShippingCost();
        $this->total = $so->getTotal();
        $this->notes = $so->getNotes();
        $this->sourceWhatsapp = $so->getSourceWhatsapp();
        $this->estimatedDelivery = $so->getEstimatedDelivery()?->format('Y-m-d');
        $this->deliveredAt = $so->getDeliveredAt()?->format(\DateTimeInterface::ATOM);
        $this->itemCount = $so->getItems()->count();
        $this->statusHistoryCount = $so->getStatusHistory()->count();
        $this->createdAt = $so->getCreatedAt()->format(\DateTimeInterface::ATOM);
        $this->updatedAt = $so->getUpdatedAt()->format(\DateTimeInterface::ATOM);
    }
}
