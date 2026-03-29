<?php

declare(strict_types=1);

namespace App\DTO\Response;

use App\Entity\Sale;

final readonly class SaleDetailResponse
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
    public array $saleItems;
    public array $payments;
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
        $this->saleItems = array_map(static fn ($item) => [
            'id' => $item->getId(),
            'product' => [
                'id' => $item->getProduct()->getId(),
                'name' => $item->getProduct()->getName(),
                'price' => $item->getProduct()->getPrice(),
            ],
            'quantity' => $item->getQuantity(),
            'unitPrice' => $item->getUnitPrice(),
            'totalPrice' => $item->getTotalPrice(),
            'taxAmount' => $item->getTaxAmount(),
            'discountAmount' => $item->getDiscountAmount(),
        ], $sale->getSaleItems()->toArray());
        $this->payments = array_map(static fn ($payment) => [
            'id' => $payment->getId(),
            'amount' => $payment->getAmount(),
            'method' => $payment->getMethod(),
            'status' => $payment->getStatus(),
            'transactionId' => $payment->getTransactionId(),
            'notes' => $payment->getNotes(),
            'createdAt' => $payment->getCreatedAt()->format(\DateTimeInterface::ATOM),
        ], $sale->getPayments()->toArray());
        $this->createdAt = $sale->getCreatedAt()->format(\DateTimeInterface::ATOM);
        $this->updatedAt = $sale->getUpdatedAt()->format(\DateTimeInterface::ATOM);
    }
}
