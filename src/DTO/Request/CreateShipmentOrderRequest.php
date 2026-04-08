<?php

declare(strict_types=1);

namespace App\DTO\Request;

use Symfony\Component\Validator\Constraints as Assert;

final readonly class CreateShipmentOrderRequest
{
    public function __construct(
        #[Assert\NotNull]
        #[Assert\Positive]
        public int $salesOrderId,

        #[Assert\NotNull]
        #[Assert\Positive]
        public int $warehouseId,

        public ?string $carrier = null,

        public ?string $notes = null,
    ) {}
}
