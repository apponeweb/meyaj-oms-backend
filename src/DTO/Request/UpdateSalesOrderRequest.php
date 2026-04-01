<?php

declare(strict_types=1);

namespace App\DTO\Request;

final readonly class UpdateSalesOrderRequest
{
    public function __construct(
        public ?string $status = null,
        public ?string $paymentStatus = null,
        public ?string $deliveryStatus = null,
        public ?string $notes = null,
        public ?string $estimatedDelivery = null,
    ) {}
}
