<?php

declare(strict_types=1);

namespace App\DTO\Response;

use App\Entity\Sale;

final readonly class SaleResponse
{
    public int $id;
    public ?array $customer;
    public string $subtotal;
    public string $tax;
    public string $discount;
    public string $total;
    public string $status;
    public string $paymentMethod;
    public ?string $notes;
    public string $createdAt;
    public string $updatedAt;

    public function __construct(Sale $sale)
    {
        $this->id = $sale->getId();
        $this->customer = $sale->getCustomer() ? [
            'id' => $sale->getCustomer()->getId(),
            'name' => $sale->getCustomer()->getName(),
            'email' => $sale->getCustomer()->getEmail(),
            'phone' => $sale->getCustomer()->getPhone(),
        ] : null;
        $this->subtotal = $sale->getSubtotal();
        $this->tax = $sale->getTax();
        $this->discount = $sale->getDiscount();
        $this->total = $sale->getTotal();
        $this->status = $sale->getStatus();
        $this->paymentMethod = $sale->getPaymentMethod();
        $this->notes = $sale->getNotes();
        $this->createdAt = $sale->getCreatedAt()->format(\DateTimeInterface::ATOM);
        $this->updatedAt = $sale->getUpdatedAt()->format(\DateTimeInterface::ATOM);
    }
}
