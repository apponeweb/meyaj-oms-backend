<?php

declare(strict_types=1);

namespace App\DTO\Request;

final readonly class UpdatePurchaseOrderRequest
{
    public function __construct(
        public ?string $status = null,
        public ?string $orderDate = null,
        public ?string $expectedDate = null,
        public ?string $notes = null,
    ) {}
}
